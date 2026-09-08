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
 *
 * 08/09/2026: guarda também ONDE a plataforma (app.ia365) recebe avisos de
 * integração ligada/desligada (`plataforma_url` + token que registrou).
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
        'plataforma_url',
        'plataforma_token_id',
        'plataforma_notificado_em',
        'plataforma_ultima_falha',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'indexado_em' => 'datetime',
            'plataforma_notificado_em' => 'datetime',
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

    public function plataformaToken(): BelongsTo
    {
        return $this->belongsTo(IntegracaoToken::class, 'plataforma_token_id');
    }

    public static function ativaPara(int $empresaId): bool
    {
        return static::where('empresa_id', $empresaId)->where('ativo', true)->exists();
    }

    /** A plataforma registrou onde quer receber avisos desta empresa? */
    public function plataformaRegistrada(): bool
    {
        return filled($this->plataforma_url) && filled($this->plataforma_token_id);
    }
}
