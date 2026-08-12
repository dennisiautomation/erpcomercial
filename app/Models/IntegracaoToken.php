<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token da API de Integração (somente leitura — rotas api/integracao/v1).
 *
 * SEM BelongsToEmpresa de propósito: o token é consultado fora de sessão
 * (request máquina-a-máquina) e administrado pelo admin da plataforma
 * (empresa_id NULL) — os dois casos em que o EmpresaScope não ajuda.
 * Todo acesso filtra empresa_id explicitamente.
 */
class IntegracaoToken extends Model
{
    protected $table = 'integracao_tokens';

    protected $fillable = [
        'empresa_id',
        'nome',
        'token_hash',
        'ativo',
        'last_used_at',
        'last_used_ip',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /**
     * Gera um token novo e devolve [model, valor em claro].
     * O valor em claro NUNCA é persistido — só o sha256.
     */
    public static function gerar(int $empresaId, string $nome, ?int $criadoPor): array
    {
        $claro = 'gsn_' . Str::random(40);

        $token = static::create([
            'empresa_id' => $empresaId,
            'nome' => $nome,
            'token_hash' => hash('sha256', $claro),
            'ativo' => true,
            'criado_por' => $criadoPor,
        ]);

        return [$token, $claro];
    }

    public static function autenticar(?string $claro): ?self
    {
        if (! $claro) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $claro))
            ->where('ativo', true)
            ->first();
    }
}
