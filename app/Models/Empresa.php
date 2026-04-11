<?php

namespace App\Models;

use App\Enums\RegimeTributario;
use App\Enums\StatusEmpresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cnpj',
        'razao_social',
        'nome_fantasia',
        'ie',
        'im',
        'regime_tributario',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'telefone',
        'email',
        'logo',
        'plano',
        'plano_id',
        'trial_inicio',
        'trial_fim',
        'assinatura_inicio',
        'assinatura_fim',
        'tipo_cobranca',
        'em_trial',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'regime_tributario' => RegimeTributario::class,
            'status'            => StatusEmpresa::class,
            'trial_inicio'      => 'date',
            'trial_fim'         => 'date',
            'assinatura_inicio' => 'date',
            'assinatura_fim'    => 'date',
            'em_trial'          => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function planoAssinatura(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    public function fornecedores(): HasMany
    {
        return $this->hasMany(Fornecedor::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(Servico::class);
    }

    public function transportadoras(): HasMany
    {
        return $this->hasMany(Transportadora::class);
    }

    /* ------------------------------------------------------------------ */
    /*  Subscription Helpers                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Check if the trial period is still active.
     */
    public function isTrialActive(): bool
    {
        return $this->em_trial && $this->trial_fim && $this->trial_fim->gte(Carbon::today());
    }

    /**
     * Check if the empresa has an active subscription (paid or trial).
     */
    public function isAssinaturaAtiva(): bool
    {
        if ($this->isTrialActive()) {
            return true;
        }

        return $this->assinatura_fim && $this->assinatura_fim->gte(Carbon::today());
    }

    /**
     * Get the number of remaining trial days (0 if not in trial or expired).
     */
    public function diasRestantesTrial(): int
    {
        if (! $this->em_trial || ! $this->trial_fim) {
            return 0;
        }

        $dias = Carbon::today()->diffInDays($this->trial_fim, false);

        return max(0, (int) $dias);
    }

    /**
     * Check if the empresa can add more of a given resource.
     *
     * Accepted resources: unidades, usuarios, produtos, notas.
     */
    public function podeAdicionar(string $resource): bool
    {
        return ! $this->limiteAtingido($resource);
    }

    /**
     * Check if the limit for a resource has been reached.
     */
    public function limiteAtingido(string $resource): bool
    {
        $plano = $this->getPlanoAtivo();

        if (! $plano) {
            return true;
        }

        $limite = $plano->getLimit($resource);

        $atual = match ($resource) {
            'unidades' => $this->unidades()->count(),
            'usuarios' => $this->users()->count(),
            'produtos' => $this->produtos()->count(),
            'notas'    => $this->notasFiscaisDoMes(),
            default    => 0,
        };

        return $atual >= $limite;
    }

    /**
     * Get the active plan (via plano_id relationship).
     */
    public function getPlanoAtivo(): ?Plano
    {
        return $this->planoAssinatura;
    }

    /**
     * Count notas fiscais emitted this month for this empresa.
     */
    public function notasFiscaisDoMes(): int
    {
        if (! class_exists(\App\Models\NotaFiscal::class)) {
            return 0;
        }

        return \App\Models\NotaFiscal::withoutGlobalScopes()
            ->where('empresa_id', $this->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
    }
}
