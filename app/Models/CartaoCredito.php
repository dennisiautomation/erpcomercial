<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cartão de crédito de uma conta conectada pelo Open Finance (17/11/2026). Não conta como conta extra. */
class CartaoCredito extends Model
{
    use BelongsToEmpresa;

    protected $table = 'cartoes_credito';

    protected $fillable = [
        'empresa_id', 'conta_bancaria_id', 'final', 'adicionais', 'apelido', 'importar', 'limite', 'limite_disponivel',
        'protocolo_pendente', 'protocolo_inicio', 'protocolo_fim', 'ultima_sincronizacao_em', 'proxima_sincronizacao_em', 'ultimo_erro',
    ];

    protected function casts(): array
    {
        return [
            'importar' => 'boolean',
            'limite' => 'decimal:2',
            'limite_disponivel' => 'decimal:2',
            'protocolo_inicio' => 'date',
            'protocolo_fim' => 'date',
            'ultima_sincronizacao_em' => 'datetime',
            'proxima_sincronizacao_em' => 'datetime',
        ];
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class);
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(LancamentoBancario::class);
    }

    public function nome(): string
    {
        return $this->apelido ?: 'Cartão final ' . $this->final;
    }
}
