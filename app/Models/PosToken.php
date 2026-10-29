<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token do operador no app da maquininha Stone (api/integracao/pos/v1).
 *
 * SEM BelongsToEmpresa de propósito (igual ao IntegracaoToken): é consultado
 * antes de existir usuário autenticado. Todo acesso filtra empresa_id na mão.
 */
class PosToken extends Model
{
    /** Validade do login no POS. Turno longo + folga; depois disso, novo login. */
    public const VALIDADE_HORAS = 16;

    protected $table = 'pos_tokens';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'unidade_id',
        'token_hash',
        'dispositivo_serial',
        'dispositivo_modelo',
        'app_versao',
        'expira_em',
        'revogado_em',
        'last_used_at',
        'last_used_ip',
    ];

    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'revogado_em' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class)->withoutGlobalScopes();
    }

    /** Gera um token novo e devolve [model, valor em claro]. O claro nunca é salvo. */
    public static function gerar(User $user, ?int $unidadeId, array $dispositivo): array
    {
        $claro = 'pos_' . Str::random(48);

        $token = static::create([
            'empresa_id' => $user->empresa_id,
            'user_id' => $user->id,
            'unidade_id' => $unidadeId,
            'token_hash' => hash('sha256', $claro),
            'dispositivo_serial' => $dispositivo['serial'] ?? null,
            'dispositivo_modelo' => $dispositivo['modelo'] ?? null,
            'app_versao' => $dispositivo['app_versao'] ?? null,
            'expira_em' => now()->addHours(self::VALIDADE_HORAS),
        ]);

        return [$token, $claro];
    }

    public static function autenticar(?string $claro): ?self
    {
        if (! $claro || ! str_starts_with($claro, 'pos_')) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $claro))
            ->whereNull('revogado_em')
            ->where('expira_em', '>', now())
            ->first();
    }
}
