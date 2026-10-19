<?php

namespace App\Models;

use App\Enums\Perfil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Vídeo de treinamento da plataforma (sem empresa_id — vale para todas).
 * Dono e gerente veem todos; os demais perfis só os marcados em `perfis`.
 */
class Treinamento extends Model
{
    /** Perfis que o admin marca no vídeo. Dono e gerente ficam de fora: veem tudo. */
    public const PERFIS = [
        Perfil::Financeiro,
        Perfil::Vendedor,
        Perfil::Caixa,
        Perfil::Consulta,
    ];

    protected $fillable = [
        'titulo',
        'descricao',
        'arquivo',
        'tamanho',
        'ordem',
        'ativo',
        'perfis',
    ];

    protected function casts(): array
    {
        return [
            'ativo'   => 'boolean',
            'perfis'  => 'array',
            'tamanho' => 'integer',
            'ordem'   => 'integer',
        ];
    }

    public function usuariosConcluidos(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'treinamento_user')
                    ->withPivot('concluido_em');
    }

    /** Perfil do usuário como string, ou null quando não tem. */
    private static function perfilDe(User $user): ?string
    {
        return $user->perfil instanceof Perfil ? $user->perfil->value : $user->perfil;
    }

    private static function veTodos(User $user): bool
    {
        return $user->is_admin
            || in_array(self::perfilDe($user), [Perfil::Dono->value, Perfil::Gerente->value], true);
    }

    /** Vídeos que este usuário pode abrir na empresa. */
    public function scopeVisiveisPara(Builder $query, User $user): Builder
    {
        $query->where('ativo', true)->whereNotNull('arquivo');

        if (! self::veTodos($user)) {
            $query->whereJsonContains('perfis', self::perfilDe($user));
        }

        return $query->orderBy('ordem')->orderBy('id');
    }

    /** Usado no acompanhamento do admin: o vídeo é do perfil deste usuário? */
    public function aplicaA(User $user): bool
    {
        return self::veTodos($user) || in_array(self::perfilDe($user), $this->perfis ?? [], true);
    }
}
