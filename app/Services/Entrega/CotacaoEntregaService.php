<?php

namespace App\Services\Entrega;

use App\Models\EmpresaGateway;
use App\Models\Produto;
use App\Models\Unidade;
use Illuminate\Support\Facades\Log;

/**
 * Cotação de entrega — ponto ÚNICO (14/11/2026).
 *
 * Saiu do IntegracaoAgenteController (onde era um método privado) sem mudar
 * uma linha da regra, para a loja online cotar com a MESMA decisão do agente:
 * mesma cidade → Uber Direct; outra cidade → Melhor Envio pelas medidas.
 */
class CotacaoEntregaService
{
    /**
     * Decide o provedor e cota. Nunca lança: devolve `disponivel` false com
     * `motivo`/`mensagem` para quem chamou se adaptar.
     *
     * Uber é entrega LOCAL: vale quando o CEP está nas faixas cadastradas;
     * sem faixas cadastradas, vale para a mesma cidade da loja (ViaCEP) — e
     * só deixa de valer se o Melhor Envio estiver conectado (senão o
     * comportamento é o de sempre). Uber que falha cai no Melhor Envio quando
     * ele existe (Correios também entregam na cidade).
     *
     * @param  array<string, mixed>  $end  cep/logradouro/numero/bairro/cidade/uf
     * @param  array<int, array{produto: ?Produto, quantidade: float, valor_unitario: float}>  $itens
     * @return array<string, mixed>
     */
    public function resolver(int $empresaId, Unidade $unidade, array $end, array $itens = [], ?string $servicoId = null): array
    {
        $indisponivel = fn (string $motivo, string $mensagem) => ['disponivel' => false, 'motivo' => $motivo, 'mensagem' => $mensagem];

        $uber = UberDirectService::ativoPara($empresaId);
        $me = MelhorEnvioService::ativoPara($empresaId);
        if (! $uber && ! $me) {
            return $indisponivel('entrega_desativada',
                'Entrega automática não está habilitada nesta loja — um atendente pode combinar a entrega.');
        }

        $cep = preg_replace('/\D/', '', (string) ($end['cep'] ?? ''));

        $local = false;
        if ($uber && $uber->cepAtendido($cep)) {
            $local = $uber->temFaixas() || ! $me || MelhorEnvioService::mesmaCidade($cep, $unidade);
        }

        if ($local) {
            // Cidade/UF caem na unidade quando não informadas (entrega local).
            $rua = trim(($end['logradouro'] ?? '') . ' ' . ($end['numero'] ?? ''));
            $dropoff = trim(sprintf(
                '%s, %s, %s, %s, BR',
                $rua !== '' ? $rua : ($end['bairro'] ?? ''),
                $end['cidade'] ?? $unidade->cidade,
                mb_strtoupper($end['uf'] ?? $unidade->uf),
                $cep
            ), ', ');

            try {
                $quote = $uber->cotar($unidade, $dropoff);
                EmpresaGateway::ativoPara($empresaId, EmpresaGateway::PROVEDOR_UBER_DIRECT)?->update(['ultima_falha' => null]);
                $valor = round($quote['fee'] / 100, 2);

                return [
                    'disponivel' => true,
                    'provedor' => 'uber_direct',
                    'valor' => $valor,
                    'prazo_minutos' => $quote['duration'],
                    'mensagem' => 'Entrega disponível: R$ ' . number_format($valor, 2, ',', '.')
                        . ', chega em ~' . (int) $quote['duration'] . ' min após a confirmação do pagamento. O frete é somado ao total do pedido.',
                ];
            } catch (\Throwable $e) {
                Log::channel('integracao')->error('Agente IA: falha ao cotar entrega Uber', [
                    'empresa_id' => $empresaId,
                    'erro' => $e->getMessage(),
                ]);
                // Registra no gateway p/ o card da aba Integração denunciar a
                // credencial quebrada (mesma coluna do "Testar conexão").
                EmpresaGateway::ativoPara($empresaId, EmpresaGateway::PROVEDOR_UBER_DIRECT)
                    ?->update(['ultima_falha' => mb_substr($e->getMessage(), 0, 1000)]);
                if (! $me) {
                    return $indisponivel('erro_cotacao',
                        'Não consegui cotar a entrega agora — um atendente pode combinar a entrega.');
                }
                // Uber falhou, mas há Melhor Envio: segue para ele.
            }
        }

        if ($me) {
            try {
                $opcoes = $me->cotar($unidade, $cep, $itens, $servicoId);
                if ($opcoes === [] && $servicoId !== null && $servicoId !== '') {
                    // 27/09: o serviço escolhido não voltou (id errado ou saiu do ar)
                    // — recota tudo e fica com a mais barata em vez de zerar o frete.
                    $opcoes = $me->cotar($unidade, $cep, $itens);
                }
                $me->gateway()->update(['ultima_falha' => null]);
            } catch (\Throwable $e) {
                Log::channel('integracao')->error('Agente IA: falha ao cotar frete Melhor Envio', [
                    'empresa_id' => $empresaId,
                    'erro' => $e->getMessage(),
                ]);
                $me->gateway()->update(['ultima_falha' => mb_substr($e->getMessage(), 0, 1000)]);

                return $indisponivel('erro_cotacao',
                    'Não consegui cotar o frete agora — um atendente pode combinar a entrega.');
            }
            if ($opcoes === []) {
                return $indisponivel('sem_servico',
                    'Nenhuma transportadora atende este CEP com as medidas informadas — um atendente pode combinar a entrega, ou o pedido pode ser para retirada.');
            }

            $escolhida = $opcoes[0];
            if ($servicoId !== null && $servicoId !== '') {
                foreach ($opcoes as $op) {
                    if ($op['servico_id'] === (string) $servicoId) {
                        $escolhida = $op;
                        break;
                    }
                }
            }
            $lista = array_slice($opcoes, 0, 3);
            $partes = [];
            foreach ($lista as $n => $op) {
                $partes[] = ($n + 1) . ') ' . $op['nome'] . ($op['transportadora'] !== '' ? ' — ' . $op['transportadora'] : '')
                    . ': R$ ' . number_format($op['valor'], 2, ',', '.') . ', até ' . $op['prazo_dias'] . ' dias úteis (servico_id ' . $op['servico_id'] . ')';
            }

            return [
                'disponivel' => true,
                'provedor' => 'melhor_envio',
                'valor' => $escolhida['valor'],
                'prazo_dias' => $escolhida['prazo_dias'],
                'servico_id' => $escolhida['servico_id'],
                'servico_nome' => $escolhida['nome'],
                'transportadora' => $escolhida['transportadora'],
                'opcoes' => $lista,
                'mensagem' => 'Frete para o CEP ' . substr($cep, 0, 5) . '-' . substr($cep, 5) . ': ' . implode('; ', $partes)
                    . '. O frete é somado ao total do pedido e a postagem acontece após a confirmação do pagamento.',
            ];
        }

        return $indisponivel('cep_fora_da_area',
            'Este CEP está fora da área de entrega — um atendente pode combinar outra forma, ou o pedido pode ser para retirada.');
    }
}
