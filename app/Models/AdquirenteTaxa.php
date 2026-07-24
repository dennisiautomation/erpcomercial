<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class AdquirenteTaxa extends Model
{
    use BelongsToEmpresa, AuditableModel;

    protected $table = 'adquirente_taxas';

    protected $fillable = [
        'empresa_id',
        'nome',
        'forma',
        'parcelas_de',
        'parcelas_ate',
        'taxa_percentual',
        'prazo_dias',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'taxa_percentual' => 'decimal:2',
            'ativo'           => 'boolean',
        ];
    }

    /**
     * Regra ativa que cobre a forma + nº de parcelas (a mais específica:
     * menor faixa de parcelas primeiro).
     */
    public static function paraPagamento(int $empresaId, string $forma, int $parcelas = 1): ?self
    {
        return static::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('forma', $forma)
            ->where('ativo', true)
            ->where('parcelas_de', '<=', $parcelas)
            ->where('parcelas_ate', '>=', $parcelas)
            ->orderByRaw('(parcelas_ate - parcelas_de) asc')
            ->first();
    }
}
