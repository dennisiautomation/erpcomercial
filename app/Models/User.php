<?php

namespace App\Models;

use App\Enums\Perfil;
use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, BelongsToEmpresa, AuditableModel;

    protected $auditFields = ['name', 'email', 'perfil', 'status', 'comissao_percentual', 'is_admin'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_id',
        'cpf',
        'telefone',
        'perfil',
        'comissao_percentual',
        'status',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'perfil' => Perfil::class,
            'is_admin' => 'boolean',
            'comissao_percentual' => 'decimal:2',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'unidade_user')
                     ->withTimestamps();
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class, 'vendedor_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'vendedor_id');
    }

    public function orcamentos(): HasMany
    {
        return $this->hasMany(Orcamento::class, 'vendedor_id');
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(Comissao::class);
    }

    public function caixas(): HasMany
    {
        return $this->hasMany(Caixa::class, 'operador_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Helper Methods                                                     */
    /* ------------------------------------------------------------------ */

    public function isAdmin(): bool
    {
        return $this->is_admin || $this->perfil === Perfil::Admin;
    }

    public function isDono(): bool
    {
        return $this->perfil === Perfil::Dono;
    }

    public function isGerente(): bool
    {
        return $this->perfil === Perfil::Gerente;
    }

    public function hasPermission(string $modulo, string $acao): bool
    {
        if ($this->isAdmin() || $this->isDono()) {
            return true;
        }

        return $this->perfilModel?->permissoes()
            ->where('modulo', $modulo)
            ->where('acao', $acao)
            ->exists() ?? false;
    }

    public function perfilModel(): BelongsTo
    {
        return $this->belongsTo(Perfil::class, 'perfil_id');
    }
}
