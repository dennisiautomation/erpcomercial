<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtratoBancario extends Model
{
    protected $table = 'extratos_bancarios';

    protected $fillable = [
        'conciliacao_id',
        'data',
        'descricao',
        'valor',
        'tipo',
        'documento',
        'conta_receber_id',
        'conta_pagar_id',
        'conciliado',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'conciliado' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function conciliacao(): BelongsTo
    {
        return $this->belongsTo(ConciliacaoBancaria::class, 'conciliacao_id');
    }

    public function contaReceber(): BelongsTo
    {
        return $this->belongsTo(ContaReceber::class);
    }

    public function contaPagar(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class);
    }
}
