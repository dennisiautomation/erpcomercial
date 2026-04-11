<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConciliacaoBancaria extends Model
{
    use BelongsToEmpresa, SoftDeletes;

    protected $table = 'conciliacoes_bancarias';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'banco',
        'agencia',
        'conta',
        'periodo_inicio',
        'periodo_fim',
        'saldo_inicial',
        'saldo_final',
        'total_lancamentos',
        'conciliados',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fim' => 'date',
            'saldo_inicial' => 'decimal:2',
            'saldo_final' => 'decimal:2',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function extratos(): HasMany
    {
        return $this->hasMany(ExtratoBancario::class, 'conciliacao_id');
    }
}
