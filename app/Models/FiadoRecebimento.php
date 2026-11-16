<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pagamento do fiado feito pelo cliente no caixa (16/11/2026).
 *
 * Um recebimento pode abater várias contas (as mais antigas primeiro); o que
 * cada uma recebeu fica em `contas`. A entrada no caixa é a
 * `movimentacao_caixa_id` (tipo `recebimento`, na forma em que o cliente pagou).
 */
class FiadoRecebimento extends Model
{
    use BelongsToEmpresa;

    protected $table = 'fiado_recebimentos';

    protected $fillable = [
        'empresa_id', 'unidade_id', 'cliente_id', 'caixa_id', 'movimentacao_caixa_id', 'user_id',
        'numero', 'valor', 'forma_pagamento', 'forma_chave', 'forma_nome', 'contas',
        'saldo_antes', 'saldo_depois', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'valor'        => 'decimal:2',
            'saldo_antes'  => 'decimal:2',
            'saldo_depois' => 'decimal:2',
            'contas'       => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withoutGlobalScopes();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class)->withoutGlobalScopes();
    }

    public function formaLabel(): string
    {
        return $this->forma_nome ?: (\App\Services\FormaPagamentoService::ROTULOS[$this->forma_pagamento] ?? ucfirst((string) $this->forma_pagamento));
    }
}
