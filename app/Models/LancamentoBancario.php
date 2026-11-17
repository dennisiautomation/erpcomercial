<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lançamento do extrato que veio do banco pelo Open Finance (17/11/2026). */
class LancamentoBancario extends Model
{
    use BelongsToEmpresa;

    protected $table = 'lancamentos_bancarios';

    protected $fillable = [
        'empresa_id', 'conta_bancaria_id', 'transaction_id', 'data', 'tipo', 'valor', 'descricao', 'operacao',
        'contraparte_nome', 'contraparte_documento', 'linha_digitavel', 'bruto', 'status',
        'sugestao_conta_receber_id', 'sugestao_conta_pagar_id', 'sugestao_confianca', 'sugestao_motivo',
        'conta_receber_id', 'conta_pagar_id', 'conciliado_em', 'conciliado_por', 'sumiu_no_banco', 'titulo_criado',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'bruto' => 'array',
            'conciliado_em' => 'datetime',
            'sumiu_no_banco' => 'boolean',
            'titulo_criado' => 'boolean',
        ];
    }

    /** A partir daqui a sugestão é tratada como "certa" e entra no Confirmar todos. */
    public const CONFIANCA_ALTA = 80;

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class);
    }

    public function sugestaoContaReceber(): BelongsTo
    {
        return $this->belongsTo(ContaReceber::class, 'sugestao_conta_receber_id');
    }

    public function sugestaoContaPagar(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'sugestao_conta_pagar_id');
    }

    public function contaReceber(): BelongsTo
    {
        return $this->belongsTo(ContaReceber::class);
    }

    public function contaPagar(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class);
    }

    public function ehCredito(): bool
    {
        return $this->tipo === 'credito';
    }

    public function confiancaAlta(): bool
    {
        return (int) $this->sugestao_confianca >= self::CONFIANCA_ALTA;
    }
}
