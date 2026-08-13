<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Config do Agente IA por empresa.
 *
 * SEM BelongsToEmpresa de propósito (mesma razão do IntegracaoToken):
 * é consultada por jobs de fila e pela API máquina-a-máquina, fora de
 * sessão web. Todo acesso filtra empresa_id explicitamente.
 */
class AgenteIaConfig extends Model
{
    protected $table = 'agente_ia_configs';

    protected $fillable = [
        'empresa_id',
        'ativo',
        'vendedor_padrao_id',
        'indexado_em',
        'produtos_indexados',
        'ultima_falha',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'indexado_em' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function vendedorPadrao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_padrao_id');
    }

    public static function ativaPara(int $empresaId): bool
    {
        return static::where('empresa_id', $empresaId)->where('ativo', true)->exists();
    }
}
