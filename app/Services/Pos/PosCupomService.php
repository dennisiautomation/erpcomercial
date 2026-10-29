<?php

namespace App\Services\Pos;

use App\Enums\StatusNotaFiscal;
use App\Models\Caixa;
use App\Models\NotaFiscal;
use App\Models\Pedido;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;

/**
 * Cupom para a impressora térmica do POS (bobina 58 mm), em linhas JSON que o
 * app converte para o PRINTABLE_CONTENT do Deeplink da Stone:
 *
 *   {"t":"texto","v":"...","a":"esq|centro|dir","s":"p|m|g"} | {"t":"linha"} | {"t":"qrcode","v":"<url>"}
 *
 * O layout mora aqui (servidor), não no app: muda sem reenviar APK para a Stone.
 * `$colunas` é a largura em caracteres da fonte média (32 na maioria dos POS).
 */
class PosCupomService
{
    private const FORMAS = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'PIX',
        'cartao_debito' => 'Cartão de débito',
        'cartao_credito' => 'Cartão de crédito',
        'boleto' => 'Boleto',
        'transferencia' => 'Transferência',
        'crediario' => 'Crediário',
        'vale' => 'Vale troca',
        'misto' => 'Misto',
    ];

    public function __construct(private int $colunas = 32)
    {
        $this->colunas = max(24, min(48, $colunas));
    }

    public function venda(Venda $venda, ?NotaFiscal $nota = null): array
    {
        $venda->loadMissing(['itens', 'cliente', 'vendedor', 'empresa', 'unidade']);
        $nota ??= $venda->notasFiscais()->where('tipo', 'nfce')->latest()->first();
        $fiscal = $nota && $nota->status === StatusNotaFiscal::Autorizada;

        $l = $this->cabecalho($venda->empresa, $venda->unidade);

        if ($fiscal) {
            $l[] = $this->texto('DANFE NFC-e - Documento Auxiliar', 'centro', 'p');
            $l[] = $this->texto('da Nota Fiscal de Consumidor Eletrônica', 'centro', 'p');
        } else {
            $l[] = $this->texto('CUPOM NÃO FISCAL', 'centro', 'g');
        }
        $l[] = $this->texto('Venda nº ' . $venda->numero . '   ' . $venda->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i'), 'centro', 'p');
        if ($venda->status->value === 'cancelada') {
            $l[] = $this->texto('*** VENDA CANCELADA ***', 'centro', 'g');
        }
        $l[] = $this->linha();

        array_push($l, ...$this->itens($venda->itens));
        $l[] = $this->linha();

        $l[] = $this->par('Qtd. de itens', (string) $venda->itens->count());
        if ((float) $venda->desconto_valor > 0 || $venda->itens->sum('desconto_valor') > 0) {
            $l[] = $this->par('Subtotal', $this->moeda((float) $venda->itens->sum(fn ($i) => $i->preco_unitario * $i->quantidade)));
            $l[] = $this->par('Descontos', '-' . $this->moeda((float) $venda->itens->sum('desconto_valor') + (float) $venda->desconto_valor));
        }
        if ($venda->outras_despesas > 0) {
            $l[] = $this->par('Acréscimos', $this->moeda($venda->outras_despesas));
        }
        $l[] = $this->par('TOTAL R$', $this->moeda((float) $venda->total), 'g');
        $l[] = $this->linha();

        $l[] = $this->texto('FORMA DE PAGAMENTO', 'esq', 'p');
        foreach ($venda->pagamento_detalhes ?? [] as $pg) {
            $nome = self::FORMAS[$pg['forma'] ?? ''] ?? ucfirst((string) ($pg['forma'] ?? ''));
            if (! empty($pg['parcelas']) && (int) $pg['parcelas'] > 1) {
                $nome .= ' ' . (int) $pg['parcelas'] . 'x';
            }
            $l[] = $this->par($nome, $this->moeda((float) ($pg['valor'] ?? 0)));
            $detalhe = trim(implode('  ', array_filter([
                $pg['bandeira_nome'] ?? null,
                ! empty($pg['autorizacao']) ? 'Aut ' . $pg['autorizacao'] : null,
                ! empty($pg['stone_atk']) ? 'ATK ' . $pg['stone_atk'] : null,
            ])));
            if ($detalhe !== '') {
                $l[] = $this->texto($detalhe, 'esq', 'p');
            }
        }
        if ((float) $venda->troco > 0) {
            $l[] = $this->par('Troco', $this->moeda((float) $venda->troco));
        }
        $l[] = $this->linha();

        $doc = $venda->cpf_cnpj_nota ?: $venda->cliente?->cpf_cnpj;
        if ($venda->cliente) {
            $l[] = $this->texto('Cliente: ' . $venda->cliente->nome_razao_social, 'esq', 'p');
        }
        $l[] = $this->texto($doc ? 'CONSUMIDOR CPF/CNPJ: ' . $this->documento($doc) : 'CONSUMIDOR NÃO IDENTIFICADO', 'esq', 'p');

        if ($fiscal) {
            $l[] = $this->linha();
            $l[] = $this->texto('NFC-e nº ' . $nota->numero . '  Série ' . $nota->serie, 'centro', 'p');
            $l[] = $this->texto('Emissão ' . ($nota->emitida_em ?? $nota->created_at)->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s'), 'centro', 'p');
            if ($nota->url_consulta) {
                $l[] = $this->texto('Consulte pela Chave de Acesso em', 'centro', 'p');
                $l[] = $this->texto($nota->url_consulta, 'centro', 'p');
            }
            $l[] = $this->texto(trim(chunk_split((string) $nota->chave_acesso, 4, ' ')), 'centro', 'p');
            if ($nota->protocolo) {
                $l[] = $this->texto('Protocolo ' . $nota->protocolo, 'centro', 'p');
            }
            if ($nota->qrcode_url) {
                $l[] = ['t' => 'qrcode', 'v' => $nota->qrcode_url];
            }
            if ($nota->ambiente && $nota->ambiente !== 'producao') {
                $l[] = $this->texto('EMITIDA EM AMBIENTE DE HOMOLOGAÇÃO - SEM VALOR FISCAL', 'centro', 'p');
            }
        } elseif ($nota && in_array($nota->status, [StatusNotaFiscal::Pendente, StatusNotaFiscal::Contingencia], true)) {
            $l[] = $this->texto('NFC-e em processamento. Reimprima em instantes.', 'centro', 'p');
        }

        $l[] = $this->linha();
        if ($venda->vendedor) {
            $l[] = $this->texto('Vendedor: ' . $venda->vendedor->name, 'esq', 'p');
        }
        $l[] = $this->texto('Obrigado pela preferência!', 'centro', 'm');

        return $l;
    }

    public function pedido(Pedido $pedido): array
    {
        $pedido->loadMissing(['itens', 'cliente', 'vendedor', 'empresa', 'unidade']);

        $l = $this->cabecalho($pedido->empresa, $pedido->unidade);
        $l[] = $this->texto('PEDIDO Nº ' . $pedido->numero, 'centro', 'g');
        $l[] = $this->texto($pedido->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') . '  ' . $pedido->status->label(), 'centro', 'p');
        $l[] = $this->texto('Não é documento fiscal', 'centro', 'p');
        $l[] = $this->linha();

        if ($pedido->cliente) {
            $l[] = $this->texto('Cliente: ' . $pedido->cliente->nome_razao_social, 'esq', 'm');
            if ($pedido->cliente->cpf_cnpj) {
                $l[] = $this->texto('CPF/CNPJ: ' . $this->documento($pedido->cliente->cpf_cnpj), 'esq', 'p');
            }
            if ($pedido->cliente->telefone) {
                $l[] = $this->texto('Telefone: ' . $pedido->cliente->telefone, 'esq', 'p');
            }
            $l[] = $this->linha();
        }

        array_push($l, ...$this->itens($pedido->itens));
        $l[] = $this->linha();
        if ((float) $pedido->desconto_valor > 0) {
            $l[] = $this->par('Subtotal', $this->moeda((float) $pedido->subtotal));
            $l[] = $this->par('Desconto', '-' . $this->moeda((float) $pedido->desconto_valor));
        }
        $l[] = $this->par('TOTAL R$', $this->moeda((float) $pedido->total), 'g');
        if ($pedido->condicao_pagamento) {
            $l[] = $this->texto('Condição: ' . $pedido->condicao_pagamento, 'esq', 'p');
        }
        if ($pedido->observacoes_externas) {
            $l[] = $this->linha();
            $l[] = $this->texto($pedido->observacoes_externas, 'esq', 'p');
        }
        $l[] = $this->linha();
        if ($pedido->vendedor) {
            $l[] = $this->texto('Vendedor: ' . $pedido->vendedor->name, 'esq', 'p');
        }
        $l[] = $this->texto(' ', 'esq', 'p');
        $l[] = $this->texto(str_repeat('_', $this->colunas - 4), 'centro', 'p');
        $l[] = $this->texto('Assinatura do cliente', 'centro', 'p');

        return $l;
    }

    public function fechamentoCaixa(Caixa $caixa, array $resumo, ?User $operador): array
    {
        $unidade = Unidade::withoutGlobalScopes()->find($caixa->unidade_id);
        $empresa = $unidade?->empresa()->withoutGlobalScopes()->first();

        $l = $this->cabecalho($empresa, $unidade);
        $aberto = $caixa->status->value === 'aberto';
        $l[] = $this->texto($aberto ? 'RESUMO DO CAIXA' : 'FECHAMENTO DE CAIXA', 'centro', 'g');
        $l[] = $this->texto('Caixa ' . $caixa->numero_caixa . ($operador ? ' - ' . $operador->name : ''), 'centro', 'p');
        $l[] = $this->texto('Aberto ' . $caixa->aberto_em->timezone('America/Sao_Paulo')->format('d/m H:i')
            . ($caixa->fechado_em ? '  Fechado ' . $caixa->fechado_em->timezone('America/Sao_Paulo')->format('d/m H:i') : ''), 'centro', 'p');
        $l[] = $this->linha();

        $l[] = $this->par('Vendas', (string) $resumo['qtd_vendas']);
        foreach ($resumo['vendas_por_forma'] as $forma => $valor) {
            $l[] = $this->par(self::FORMAS[$forma] ?? $forma, $this->moeda((float) $valor));
        }
        $l[] = $this->par('Total vendido', $this->moeda((float) $resumo['vendas']), 'm');
        if ($resumo['estornos'] > 0) {
            $l[] = $this->par('Estornos', '-' . $this->moeda((float) $resumo['estornos']));
        }
        $l[] = $this->linha();
        $l[] = $this->texto('GAVETA (DINHEIRO)', 'esq', 'p');
        $l[] = $this->par('Abertura', $this->moeda((float) $resumo['abertura']));
        $l[] = $this->par('Vendas em dinheiro', $this->moeda((float) $resumo['vendas_dinheiro']));
        $l[] = $this->par('Suprimentos', $this->moeda((float) $resumo['suprimentos']));
        $l[] = $this->par('Sangrias', '-' . $this->moeda((float) $resumo['sangrias']));
        if ($resumo['devolucoes'] > 0) {
            $l[] = $this->par('Devoluções', '-' . $this->moeda((float) $resumo['devolucoes']));
        }
        $l[] = $this->par('Esperado', $this->moeda((float) $resumo['esperado_dinheiro']), 'm');

        foreach ((array) $caixa->conferencia as $forma => $c) {
            if ($forma === 'dinheiro') {
                $l[] = $this->par('Contado', $this->moeda((float) $c['contado']), 'm');
                $l[] = $this->par('Diferença', $this->moeda((float) $c['diferenca']), 'm');
            }
        }
        $l[] = $this->linha();
        $l[] = $this->texto(' ', 'esq', 'p');
        $l[] = $this->texto(str_repeat('_', $this->colunas - 4), 'centro', 'p');
        $l[] = $this->texto('Assinatura do operador', 'centro', 'p');

        return $l;
    }

    // ─── Blocos ───

    private function cabecalho($empresa, ?Unidade $unidade): array
    {
        $l = [];
        $nome = $empresa?->nome_fantasia ?: $empresa?->razao_social ?: ($unidade?->nome ?? '');
        $l[] = $this->texto($nome, 'centro', 'g');
        if ($empresa?->razao_social && $empresa->razao_social !== $nome) {
            $l[] = $this->texto($empresa->razao_social, 'centro', 'p');
        }
        $cnpj = $unidade?->cnpj ?: $empresa?->cnpj;
        if ($cnpj) {
            $l[] = $this->texto('CNPJ ' . $this->documento($cnpj) . ($unidade?->ie ? '  IE ' . $unidade->ie : ''), 'centro', 'p');
        }
        if ($unidade) {
            $end = trim(implode(', ', array_filter([$unidade->logradouro, $unidade->numero, $unidade->bairro])));
            if ($end !== '') {
                $l[] = $this->texto($end, 'centro', 'p');
            }
            if ($unidade->cidade) {
                $l[] = $this->texto($unidade->cidade . ($unidade->uf ? '/' . $unidade->uf : ''), 'centro', 'p');
            }
        }
        $l[] = $this->linha();

        return $l;
    }

    private function itens($itens): array
    {
        $l = [];
        foreach ($itens as $n => $item) {
            $l[] = $this->texto(str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT) . ' ' . $item->descricao, 'esq', 'p');
            $qtd = rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', ''), '0'), ',');
            $l[] = $this->par('    ' . $qtd . ' x ' . $this->moeda((float) $item->preco_unitario), $this->moeda((float) $item->total));
            if ((float) $item->desconto_valor > 0) {
                $l[] = $this->par('    desconto', '-' . $this->moeda((float) $item->desconto_valor), 'p');
            }
        }

        return $l;
    }

    // ─── Primitivas ───

    private function texto(string $v, string $a = 'esq', string $s = 'm'): array
    {
        return ['t' => 'texto', 'v' => $v, 'a' => $a, 's' => $s];
    }

    private function linha(): array
    {
        return ['t' => 'linha'];
    }

    /** Rótulo à esquerda e valor à direita na mesma linha (fonte média). */
    private function par(string $rotulo, string $valor, string $s = 'm'): array
    {
        $cols = $s === 'g' ? intdiv($this->colunas * 2, 3) : $this->colunas;
        $espaco = $cols - mb_strlen($rotulo) - mb_strlen($valor);
        if ($espaco < 1) {
            $rotulo = mb_substr($rotulo, 0, max(1, $cols - mb_strlen($valor) - 1));
            $espaco = 1;
        }

        return $this->texto($rotulo . str_repeat(' ', $espaco) . $valor, 'esq', $s === 'g' ? 'g' : 'm');
    }

    private function moeda(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }

    private function documento(string $doc): string
    {
        $d = preg_replace('/[^0-9A-Za-z]/', '', $doc);
        if (strlen($d) === 11) {
            return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9);
        }
        if (strlen($d) === 14) {
            return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12);
        }

        return $doc;
    }
}
