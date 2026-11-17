<?php

namespace App\Services\OpenFinance;

use App\Models\CentroCusto;
use App\Models\ContaPagar;
use App\Models\Fornecedor;
use App\Models\LancamentoBancario;
use App\Models\PlanoContas;
use App\Models\RegraConciliacao;
use App\Models\Unidade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saída do banco sem conta a pagar cadastrada (17/11/2026): o sistema propõe a despesa já com
 * favorecido, conta do plano de contas e centro de custo, e a pessoa só confirma.
 *
 * De onde vem a proposta, nesta ordem:
 *  1. regra aprendida para o CPF/CNPJ de quem recebeu;
 *  2. regra aprendida para o texto do banco (tarifa, juros — lançamento sem CPF/CNPJ);
 *  3. histórico: a última conta a pagar do mesmo fornecedor que já tinha classificação.
 * Cada confirmação grava ou reforça a regra.
 */
class ClassificadorLancamentos
{
    /**
     * @return array{origem: ?string, usos: int, fornecedor_id: ?int, favorecido: string, descricao: string,
     *               plano_conta_id: ?int, plano_nome: ?string, centro_custo_id: ?int, centro_nome: ?string}
     */
    public function propor(LancamentoBancario $l): array
    {
        $fornecedor = $this->fornecedorPorDocumento($l->empresa_id, $l->contraparte_documento);
        $proposta = [
            'origem' => null, 'usos' => 0,
            'fornecedor_id' => $fornecedor?->id,
            'favorecido' => $fornecedor?->razao_social ?: $this->nomeDoBanco($l),
            'descricao' => $this->descricaoPadrao($l),
            'plano_conta_id' => null, 'plano_nome' => null, 'centro_custo_id' => null, 'centro_nome' => null,
        ];

        $regra = $this->regraPara($l);
        if ($regra) {
            return array_merge($proposta, [
                'origem' => 'regra', 'usos' => (int) $regra->usos,
                'fornecedor_id' => $regra->fornecedor_id ?: $proposta['fornecedor_id'],
                'favorecido' => $regra->fornecedor?->razao_social ?: ($regra->favorecido_nome ?: $proposta['favorecido']),
                'descricao' => $l->ehDeCartao() ? $proposta['descricao'] : ($regra->descricao_padrao ?: $proposta['descricao']),
                'plano_conta_id' => $regra->plano_conta_id, 'plano_nome' => $regra->planoConta?->nome,
                'centro_custo_id' => $regra->centro_custo_id, 'centro_nome' => $regra->centroCusto?->nome,
            ]);
        }

        if ($fornecedor) {
            $anterior = ContaPagar::withoutGlobalScopes()
                ->where('empresa_id', $l->empresa_id)->where('fornecedor_id', $fornecedor->id)
                ->where(fn ($q) => $q->whereNotNull('plano_conta_id')->orWhereNotNull('centro_custo_id'))
                ->with(['planoConta', 'centroCusto'])
                ->orderByDesc('id')->first();

            if ($anterior) {
                return array_merge($proposta, [
                    'origem' => 'historico',
                    'plano_conta_id' => $anterior->plano_conta_id, 'plano_nome' => $anterior->planoConta?->nome,
                    'centro_custo_id' => $anterior->centro_custo_id, 'centro_nome' => $anterior->centroCusto?->nome,
                ]);
            }
        }

        return $proposta;
    }

    /**
     * Cria a conta a pagar JÁ PAGA com a data e o valor do banco, liga ao lançamento e aprende.
     * Não mexe em caixa: é a mesma baixa da tela de Contas a Pagar.
     */
    public function lancar(LancamentoBancario $l, ?int $usuarioId, array $dados, ?int $unidadeId = null): ContaPagar
    {
        if ($l->ehCredito()) {
            throw new \DomainException('Só saídas do banco viram despesa por aqui.');
        }
        if (! in_array($l->status, ['pendente', 'sugerido'], true)) {
            throw new \DomainException('Este lançamento já foi tratado.');
        }

        $plano = ! empty($dados['plano_conta_id'])
            ? PlanoContas::withoutGlobalScopes()->where('empresa_id', $l->empresa_id)->find($dados['plano_conta_id']) : null;
        $centro = ! empty($dados['centro_custo_id'])
            ? CentroCusto::withoutGlobalScopes()->where('empresa_id', $l->empresa_id)->find($dados['centro_custo_id']) : null;
        $fornecedor = ! empty($dados['fornecedor_id'])
            ? Fornecedor::withoutGlobalScopes()->where('empresa_id', $l->empresa_id)->find($dados['fornecedor_id'])
            : $this->fornecedorPorDocumento($l->empresa_id, $l->contraparte_documento);
        $descricao = trim((string) ($dados['descricao'] ?? '')) ?: $this->descricaoPadrao($l);
        $favorecido = $fornecedor ? null : (trim((string) ($dados['favorecido_nome'] ?? '')) ?: ($this->nomeDoBanco($l) ?: null));

        $unidadeId ??= $l->contaBancaria?->unidade_id
            ?? Unidade::withoutGlobalScopes()->where('empresa_id', $l->empresa_id)->orderBy('id')->value('id');

        $conta = DB::transaction(function () use ($l, $usuarioId, $plano, $centro, $fornecedor, $descricao, $favorecido, $unidadeId) {
            $conta = ContaPagar::withoutGlobalScopes()->create([
                'empresa_id' => $l->empresa_id,
                'unidade_id' => $unidadeId,
                'fornecedor_id' => $fornecedor?->id,
                'favorecido_nome' => $favorecido ? Str::limit($favorecido, 250, '') : null,
                'descricao' => Str::limit($descricao, 250, ''),
                'valor' => $l->valor,
                'valor_pago' => $l->valor,
                'vencimento' => $l->data,
                'competencia' => $l->data->copy()->startOfMonth(),
                'pago_em' => $l->data,
                'plano_conta_id' => $plano?->id,
                'categoria' => $plano ? Str::limit($plano->nome, 100, '') : null,
                'centro_custo_id' => $centro?->id,
                'centro_custo' => $centro ? Str::limit($centro->nome, 100, '') : null,
                'forma_pagamento' => $l->ehDeCartao() ? 'cartao_credito' : $this->forma($l->operacao),
                'parcela' => 1,
                'total_parcelas' => 1,
                'status' => 'paga',
                'observacoes' => $l->ehDeCartao()
                    ? 'Compra no ' . ($l->cartao?->nome() ?? 'cartão de crédito') . ' (Open Finance).'
                    : 'Lançada pelo extrato do banco (Open Finance).',
            ]);

            $l->update([
                'status' => 'conciliado',
                'conta_pagar_id' => $conta->id,
                'conciliado_em' => now(),
                'conciliado_por' => $usuarioId,
                'titulo_criado' => true,
                'sugestao_conta_receber_id' => null, 'sugestao_conta_pagar_id' => null,
                'sugestao_confianca' => null, 'sugestao_motivo' => null,
            ]);

            return $conta;
        });

        if ($dados['lembrar'] ?? true) {
            $this->aprender($l, $conta);
        }

        return $conta;
    }

    /** Grava ou reforça a regra a partir de uma conta a pagar conciliada com este lançamento. */
    public function aprender(LancamentoBancario $l, ContaPagar $conta): void
    {
        if ($l->ehCredito() || (! $conta->plano_conta_id && ! $conta->centro_custo_id)) {
            return;
        }

        [$tipo, $chave] = $this->chave($l);
        if ($chave === null) {
            return;
        }

        $regra = RegraConciliacao::withoutGlobalScopes()->firstOrNew([
            'empresa_id' => $l->empresa_id, 'chave_tipo' => $tipo, 'chave' => $chave,
        ]);
        $regra->fill([
            'fornecedor_id' => $conta->fornecedor_id,
            'favorecido_nome' => $conta->fornecedor_id ? null : ($conta->favorecido_nome ?: $l->contraparte_nome),
            // conta de folha/imposto tem descrição própria de cada mês: não vira texto padrão
            'descricao_padrao' => $conta->serie_id || $conta->folha_ref || $l->ehDeCartao() ? $regra->descricao_padrao : $conta->descricao,
            'plano_conta_id' => $conta->plano_conta_id,
            'centro_custo_id' => $conta->centro_custo_id,
            'usos' => $regra->exists ? $regra->usos + 1 : 1,
            'ultima_vez_em' => now(),
        ])->save();
    }

    private function regraPara(LancamentoBancario $l): ?RegraConciliacao
    {
        [$tipo, $chave] = $this->chave($l);
        if ($chave === null) {
            return null;
        }

        return RegraConciliacao::withoutGlobalScopes()
            ->with(['planoConta', 'centroCusto', 'fornecedor'])
            ->where('empresa_id', $l->empresa_id)->where('chave_tipo', $tipo)->where('chave', $chave)->first();
    }

    /** @return array{0: string, 1: ?string} documento de quem recebeu; sem ele, o texto do banco sem números */
    private function chave(LancamentoBancario $l): array
    {
        if ($l->contraparte_documento) {
            return ['documento', $l->contraparte_documento];
        }

        $texto = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $l->descricao) ?: (string) $l->descricao);
        $texto = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z ]/', ' ', $texto)));

        return ['descricao', strlen($texto) >= 5 ? Str::limit($texto, 118, '') : null];
    }

    private function fornecedorPorDocumento(int $empresaId, ?string $documento): ?Fornecedor
    {
        if (! $documento) {
            return null;
        }

        // O cadastro guarda o documento com ou sem pontuação: compara só os números.
        return Fornecedor::withoutGlobalScopes()->where('empresa_id', $empresaId)->get(['id', 'cpf_cnpj', 'razao_social'])
            ->first(fn ($f) => preg_replace('/\D/', '', (string) $f->cpf_cnpj) === $documento);
    }

    private function descricaoPadrao(LancamentoBancario $l): string
    {
        // Compra no cartão: o texto é o nome da loja; parcelada ganha "(2/4)".
        if ($l->ehDeCartao()) {
            $parcela = $l->parcela_total > 1 ? " ({$l->parcela_numero}/{$l->parcela_total})" : '';

            return Str::limit(trim((string) $l->descricao) ?: 'Compra no cartão', 240, '') . $parcela;
        }

        $quem = $this->nomeDoBanco($l) !== '' ? Str::title(Str::lower($this->nomeDoBanco($l))) : null;

        return Str::limit($quem ? "Pagamento a {$quem}" : ($l->descricao ?: 'Saída do banco'), 250, '');
    }

    /**
     * Nome de quem recebeu. Alguns bancos mandam o CPF/CNPJ no lugar do nome: aí vale o nome que
     * vem no texto do lançamento ("Pix enviado FULANO DE TAL").
     */
    public function nomeDoBanco(LancamentoBancario $l): string
    {
        $nome = trim((string) $l->contraparte_nome);
        if ($nome !== '' && ! preg_match('/^[\d.\-\/ ]+$/', $nome)) {
            return $nome;
        }

        $texto = trim(preg_replace('/^(pix (enviado|recebido)|pagamento de pix( qr code)?|transferencia (enviada|recebida)|ted (enviada|recebida)|pagamento (de )?boleto|pagamento)\s*/i', '', (string) $l->descricao));

        return preg_match('/\pL{3,}/u', $texto) ? $texto : '';
    }

    private function forma(?string $operacao): ?string
    {
        return match (strtoupper((string) $operacao)) {
            'PIX' => 'pix',
            'BOLETO' => 'boleto',
            'TED', 'DOC', 'TRANSFERENCIA' => 'transferencia',
            'CARTAO', 'CARTAO_CREDITO' => 'cartao_credito',
            default => null,
        };
    }
}
