<?php

namespace App\Services\LojaOnline;

use App\Enums\CanalVenda;
use App\Enums\StatusPedido;
use App\Models\Cliente;
use App\Models\LojaOnline;
use App\Models\Pedido;
use App\Models\Unidade;
use App\Scopes\EmpresaScope;
use App\Scopes\UnidadeScope;
use App\Services\Entrega\CotacaoEntregaService;
use App\Services\Entrega\MelhorEnvioService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fecha a compra da loja online: carrinho → PEDIDO do ERP (14/11/2026).
 *
 * Onde cada coisa vive: a loja online NÃO tem pedido próprio. A compra vira um
 * Pedido de venda comum (rascunho, canal "online"), igual ao que o agente do
 * WhatsApp cria — a equipe fatura no ERP como sempre, e é o faturamento que
 * baixa o estoque e lança o caixa. Pagamento confirmado (PIX/cartão) muda o
 * pedido para "confirmado" pelos mesmos webhooks do agente.
 */
class CheckoutLoja
{
    public function __construct(private readonly CotacaoEntregaService $cotacao)
    {
    }

    public function unidade(LojaOnline $loja): ?Unidade
    {
        return $loja->unidade_id
            ? Unidade::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $loja->empresa_id)->whereKey($loja->unidade_id)->first()
            : null;
    }

    /**
     * Cota o frete do carrinho para um CEP (mesma regra do agente). Nunca lança.
     *
     * @param  Collection<int, array<string, mixed>>  $itens  itens do CarrinhoLoja
     * @return array<string, mixed>  disponivel, opcoes[] {id, nome, valor, prazo}, endereco, mensagem
     */
    public function cotar(LojaOnline $loja, Collection $itens, array $endereco, ?string $servicoId = null): array
    {
        $unidade = $this->unidade($loja);
        $cep = preg_replace('/\D/', '', (string) ($endereco['cep'] ?? ''));
        if (! $unidade || strlen($cep) !== 8) {
            return ['disponivel' => false, 'opcoes' => [], 'mensagem' => 'Informe um CEP válido (8 números).'];
        }

        $itensCotacao = $itens->map(fn ($i) => [
            'produto' => $i['produto'], 'quantidade' => (float) $i['quantidade'], 'valor_unitario' => (float) $i['preco'],
        ])->all();

        try {
            $r = $this->cotacao->resolver((int) $loja->empresa_id, $unidade, ['cep' => $cep] + $endereco, $itensCotacao, $servicoId);
        } catch (\Throwable $e) {
            Log::channel('integracao')->warning('Loja online: cotação falhou', ['empresa_id' => $loja->empresa_id, 'erro' => $e->getMessage()]);
            $r = ['disponivel' => false, 'motivo' => 'erro_cotacao'];
        }

        $cepEnd = MelhorEnvioService::consultarCep($cep);

        if (empty($r['disponivel'])) {
            return [
                'disponivel' => false, 'opcoes' => [], 'endereco' => $cepEnd, 'motivo' => $r['motivo'] ?? null,
                'mensagem' => match ($r['motivo'] ?? '') {
                    'entrega_desativada' => 'Esta loja combina a entrega diretamente com você depois do pedido.',
                    'cep_fora_da_area', 'sem_servico' => 'Não encontramos entrega para este CEP. Você pode retirar na loja ou combinar a entrega.',
                    default => 'Não conseguimos calcular o frete agora. Você pode retirar na loja ou combinar a entrega.',
                },
            ];
        }

        if (($r['provedor'] ?? '') === 'melhor_envio') {
            $opcoes = array_map(fn ($o) => [
                'id' => (string) $o['servico_id'],
                'nome' => trim($o['nome'] . ($o['transportadora'] !== '' ? ' · ' . $o['transportadora'] : '')),
                'valor' => (float) $o['valor'],
                'prazo' => 'até ' . (int) $o['prazo_dias'] . ' dia' . ((int) $o['prazo_dias'] === 1 ? '' : 's') . ' úteis',
            ], $r['opcoes'] ?? []);
        } else {
            $opcoes = [[
                'id' => 'local',
                'nome' => 'Entrega na sua cidade',
                'valor' => (float) $r['valor'],
                'prazo' => 'cerca de ' . (int) ($r['prazo_minutos'] ?? 60) . ' min depois do pagamento',
            ]];
        }

        return ['disponivel' => true, 'provedor' => $r['provedor'], 'opcoes' => $opcoes, 'endereco' => $cepEnd, 'bruto' => $r, 'mensagem' => ''];
    }

    /**
     * Cria o pedido. $dados já vem validado pelo controller.
     *
     * @param  Collection<int, array<string, mixed>>  $itens  itens do CarrinhoLoja (conferidos agora)
     * @param  array{nome: string, telefone: string, email?: ?string, cpf_cnpj?: ?string, observacoes?: ?string,
     *               entrega: string, cep?: ?string, logradouro?: ?string, numero?: ?string, complemento?: ?string,
     *               bairro?: ?string, cidade?: ?string, uf?: ?string, servico_id?: ?string, pagamento: string}  $dados
     * @return array{pedido: Pedido, frete_combinar: bool}
     */
    public function criarPedido(LojaOnline $loja, Collection $itens, array $dados): array
    {
        $unidade = $this->unidade($loja) ?? throw new \RuntimeException('Loja online sem loja de origem.');

        // Frete: recalculado AQUI, no servidor — o valor que veio da tela não vale.
        $frete = null;
        $freteCombinar = false;
        if ($dados['entrega'] === 'entrega') {
            $cot = $this->cotar($loja, $itens, $dados, ($dados['servico_id'] ?? '') === 'local' ? null : ($dados['servico_id'] ?? null));
            if ($cot['disponivel']) {
                $frete = $cot['bruto'];
            } else {
                $freteCombinar = true;
            }
        }
        $freteValor = $frete ? (float) $frete['valor'] : null;

        $pedido = DB::transaction(function () use ($loja, $unidade, $itens, $dados, $frete, $freteValor) {
            $cliente = $this->cliente($loja, $unidade, $dados);

            $ultimoNumero = Pedido::withoutGlobalScope(EmpresaScope::class)
                ->withoutGlobalScope(UnidadeScope::class)
                ->where('empresa_id', $loja->empresa_id)
                ->lockForUpdate()
                ->max('numero');

            $subtotal = CarrinhoLoja::subtotal($itens);

            $pedido = Pedido::create([
                'empresa_id' => $loja->empresa_id,
                'unidade_id' => $unidade->id,
                'estoque_id' => $loja->estoque_id,
                'cliente_id' => $cliente->id,
                'vendedor_id' => $loja->vendedor_id,
                'numero' => $ultimoNumero ? $ultimoNumero + 1 : 1,
                'subtotal' => $subtotal,
                'desconto_percentual' => 0,
                'desconto_valor' => 0,
                'total' => round($subtotal + ($freteValor ?? 0), 2),
                'status' => StatusPedido::Rascunho,
                'metodo_entrega' => $dados['entrega'],
                'frete_valor' => $freteValor,
                'frete_provedor' => $frete['provedor'] ?? null,
                'frete_servico_id' => $frete['servico_id'] ?? null,
                'frete_servico_nome' => $frete['servico_nome'] ?? null,
                'frete_prazo_dias' => $frete['prazo_dias'] ?? null,
                'canal' => CanalVenda::Online->value,
                'loja_token' => Str::lower(Str::random(32)),
                'observacoes_internas' => "Criado pela loja online ({$loja->nome}) — telefone {$dados['telefone']}."
                    . ($dados['pagamento'] === 'combinar' ? "\nPagamento: a combinar com o cliente." : ''),
                'observacoes_externas' => filled($dados['observacoes'] ?? null) ? mb_substr((string) $dados['observacoes'], 0, 1000) : null,
            ]);

            foreach ($itens as $item) {
                $pedido->itens()->create([
                    'produto_id' => $item['id'],
                    'descricao' => $item['nome'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco'],
                    'desconto_percentual' => 0,
                    'desconto_valor' => 0,
                    'total' => $item['total'],
                ]);
            }

            return $pedido;
        });

        Log::channel('integracao')->info('Loja online: pedido criado', [
            'empresa_id' => $loja->empresa_id, 'pedido_id' => $pedido->id, 'numero' => $pedido->numero, 'total' => (float) $pedido->total,
        ]);

        return ['pedido' => $pedido, 'frete_combinar' => $freteCombinar];
    }

    /**
     * Cliente da compra: casa pelo CPF/CNPJ e, sem ele, pelo telefone (últimos 8
     * dígitos — mesma regra do agente). Endereço de entrega vira o do cadastro:
     * é dele que o despacho automático monta o destino.
     */
    private function cliente(LojaOnline $loja, Unidade $unidade, array $dados): Cliente
    {
        $digitos = preg_replace('/\D/', '', $dados['telefone']);
        $doc = preg_replace('/[^0-9A-Za-z]/', '', (string) ($dados['cpf_cnpj'] ?? ''));

        $base = fn () => Cliente::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $loja->empresa_id);

        $cliente = $doc !== '' ? $base()->where('cpf_cnpj', $doc)->orderBy('id')->first() : null;
        if (! $cliente) {
            $sufixo = mb_substr($digitos, -8);
            $cliente = $base()->where(fn ($q) => $q->where('telefone', 'like', "%{$sufixo}")->orWhere('whatsapp', 'like', "%{$sufixo}"))
                ->orderBy('id')->first();
        }

        if (! $cliente) {
            $cliente = Cliente::create([
                'empresa_id' => $loja->empresa_id,
                'tipo_pessoa' => strlen($doc) > 11 ? 'pj' : 'pf',
                'nome_razao_social' => $dados['nome'],
                'cpf_cnpj' => $doc !== '' ? $doc : null,
                'telefone' => $digitos,
                'whatsapp' => $digitos,
                'email' => $dados['email'] ?? null,
                'status' => 'ativo',
                'observacoes' => 'Cadastrado automaticamente pela loja online.',
            ]);
        } else {
            $novo = [];
            if (blank($cliente->email) && filled($dados['email'] ?? null)) {
                $novo['email'] = $dados['email'];
            }
            if (blank($cliente->cpf_cnpj) && $doc !== '') {
                $novo['cpf_cnpj'] = $doc;
            }
            if ($novo !== []) {
                $cliente->fill($novo)->save();
            }
        }

        if ($dados['entrega'] === 'entrega') {
            $cep = preg_replace('/\D/', '', (string) ($dados['cep'] ?? ''));
            $cepEnd = $cep !== '' ? MelhorEnvioService::consultarCep($cep) : null;
            $end = array_filter([
                'cep' => $cep,
                'logradouro' => $dados['logradouro'] ?? ($cepEnd['logradouro'] ?? null),
                'numero' => $dados['numero'] ?? null,
                'complemento' => $dados['complemento'] ?? null,
                'bairro' => $dados['bairro'] ?? ($cepEnd['bairro'] ?? null),
                'cidade' => $cepEnd['cidade'] ?? ($dados['cidade'] ?? $unidade->cidade),
                'uf' => mb_strtoupper((string) ($cepEnd['uf'] ?? ($dados['uf'] ?? $unidade->uf))),
            ], fn ($v) => $v !== null && $v !== '');
            if ($end !== []) {
                $cliente->fill($end)->save();
            }
        }

        return $cliente;
    }
}
