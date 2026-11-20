<?php

namespace App\Models;

use App\Enums\RegimeCobranca;
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
        'politica_estoque_inter_unidade',
        'exige_documento_cadastro',
        'vendedor_apenas_pdv',
        'pdv_vendedores_da_loja',
        'pdv_credishop',
        'usa_ficha_tecnica',
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
        'regime_cobranca',
        'cortesia_motivo',
        'cortesia_concedida_em',
        'cortesia_revisar_em',
        'cortesia_concedida_por',
        'cobranca_periodicidade',
        'cobranca_valor',
        'cobranca_dia_vencimento',
        'cobranca_proxima_renovacao',
        'cobranca_geracao',
        'cobranca_bloqueio_automatico',
        'cobranca_tolerancia_dias',
        'cobranca_suspensa_em',
        'caixa_cliente_id',
        'caixa_cliente_nome',
        'caixa_vinculado_em',
        'em_trial',
        'open_finance_contas_extras',
        'open_finance_ocultar',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'regime_tributario' => RegimeTributario::class,
            'regime_cobranca'   => RegimeCobranca::class,
            'cortesia_concedida_em' => 'date',
            'cortesia_revisar_em'   => 'date',
            'status'            => StatusEmpresa::class,
            'trial_inicio'      => 'date',
            'trial_fim'         => 'date',
            'assinatura_inicio' => 'date',
            'assinatura_fim'    => 'date',
            'em_trial'          => 'boolean',
            'open_finance_ocultar' => 'boolean',
            'open_finance_contas_extras' => 'integer',
            'cobranca_valor'                => 'decimal:2',
            'cobranca_proxima_renovacao'    => 'date',
            'cobranca_bloqueio_automatico'  => 'boolean',
            'cobranca_suspensa_em'          => 'datetime',
            'caixa_vinculado_em'            => 'datetime',
            'assinatura_solicitada_em'      => 'datetime',
            'assinatura_ativada_em'         => 'datetime',
            'exige_documento_cadastro'      => 'boolean',
            'vendedor_apenas_pdv'           => 'boolean',
            'pdv_vendedores_da_loja'        => 'boolean',
            'pdv_credishop'                 => 'boolean',
            'usa_ficha_tecnica'             => 'boolean',
        ];
    }

    /**
     * Exige CPF/CNPJ no cadastro de cliente e fornecedor?
     *
     * Default true — empresa sem a coluna preenchida (ou fora do contexto de
     * uma empresa, como o admin da plataforma) segue exigindo, que é o
     * comportamento de sempre.
     */
    public function exigeDocumentoCadastro(): bool
    {
        return (bool) ($this->exige_documento_cadastro ?? true);
    }

    /**
     * Vendedor desta empresa opera SÓ o PDV? (04/09/2026)
     *
     * Default false — empresa sem a coluna preenchida (ou contexto sem empresa,
     * como o admin da plataforma) segue com o menu completo, comportamento de
     * sempre. Alcança apenas o perfil `vendedor`; gerente, caixa, financeiro,
     * consulta e dono nunca mudam.
     */
    public function vendedorApenasPdv(): bool
    {
        return (bool) ($this->vendedor_apenas_pdv ?? false);
    }

    /**
     * O select de vendedor do PDV (F3) mostra só quem está vinculado à loja?
     * (04/09/2026)
     *
     * Default false — o select continua listando a empresa inteira, como sempre.
     * Independente do `vendedor_apenas_pdv`: são duas chaves separadas.
     */
    public function pdvVendedoresDaLoja(): bool
    {
        return (bool) ($this->pdv_vendedores_da_loja ?? false);
    }

    /**
     * O PDV oferece a forma de pagamento Credishop? (03/11/2026)
     *
     * Default false — só a STILO VINTE usa. O servidor recusa a forma para
     * empresa sem a chave (botão escondido não impede POST forjado).
     */
    public function pdvCredishop(): bool
    {
        return (bool) ($this->pdv_credishop ?? false);
    }

    /**
     * Cafeteria/alimentação (30/10/2026): tipo do produto, ficha técnica e
     * adicionais. Desligado = cadastro e PDV como sempre foram.
     */
    public function usaFichaTecnica(): bool
    {
        return (bool) ($this->usa_ficha_tecnica ?? false);
    }

    /** Visualiza estoque de outras unidades da mesma empresa? */
    public function permiteVerEstoqueOutrasUnidades(): bool
    {
        return in_array($this->politica_estoque_inter_unidade, ['ver_apenas', 'ver_e_vender'], true);
    }

    /** Pode vender produto cuja origem é outra unidade (gera transferência automática)? */
    public function permiteVenderEstoqueRemoto(): bool
    {
        return $this->politica_estoque_inter_unidade === 'ver_e_vender';
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

    public function integracaoTokens(): HasMany
    {
        return $this->hasMany(IntegracaoToken::class);
    }

    public function agenteIaConfig()
    {
        return $this->hasOne(AgenteIaConfig::class);
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
     *
     * Prioridade:
     * 1. Cobrança direta configurada (mensal/anual, pagamento direto à IA365):
     *    quem manda são as faturas — ativa enquanto não suspensa.
     * 2. Regime cortesia/parceiro/pos_pago: sempre ativa.
     * 3. Trial / assinatura (fluxo original).
     */
    public function isAssinaturaAtiva(): bool
    {
        if ($this->temCobrancaDireta()) {
            return $this->cobranca_suspensa_em === null;
        }

        if ($this->regime_cobranca && $this->regime_cobranca->ehGratuito()) {
            return true;
        }

        if ($this->isTrialActive()) {
            return true;
        }

        // Assinou pelo sistema (20/11/2026): com a 1ª mensalidade paga, vale enquanto não for suspensa
        // por atraso; antes disso, vale enquanto a 1ª mensalidade estiver dentro do prazo + tolerância.
        if ($this->assinatura_ativada_em) {
            return $this->cobranca_suspensa_em === null;
        }
        if ($this->assinatura_solicitada_em && $this->primeiraMensalidadeNoPrazo()) {
            return true;
        }

        return $this->assinatura_fim && $this->assinatura_fim->gte(Carbon::today());
    }

    /** Pediu a assinatura e a 1ª mensalidade ainda não passou do vencimento + tolerância. */
    public function primeiraMensalidadeNoPrazo(): bool
    {
        $limite = Carbon::today()->subDays((int) ($this->cobranca_tolerancia_dias ?? 0));

        return PlataformaFatura::where('empresa_id', $this->id)->where('status', 'pendente')
            ->whereDate('vencimento', '>=', $limite)->exists();
    }

    /** A pessoa se cadastrou sozinha pelo site (teste grátis)? Ver ContratacaoSite. */
    public function veioDoSite(): bool
    {
        return $this->origem_cadastro === 'site';
    }

    /** Veio do site e o teste acabou sem virar cliente: sistema inteiro bloqueado. */
    public function testeDoSiteVencido(): bool
    {
        return $this->veioDoSite() && ! $this->isAssinaturaAtiva();
    }

    /**
     * O limite do plano BARRA o cadastro? Só para empresa que veio do site (20/11/2026):
     * as criadas pelo admin têm contrato fechado à mão e várias já passam do limite do
     * plano cadastrado — para elas nada muda até o Dennis decidir.
     * Recursos: usuarios, produtos.
     */
    public function limiteDoPlanoBarra(string $resource): bool
    {
        return $this->veioDoSite() && $this->limiteAtingido($resource);
    }

    /** Cobrança direta (sem gateway) configurada pelo admin da IA365? */
    public function temCobrancaDireta(): bool
    {
        return $this->cobranca_periodicidade !== null && $this->cobranca_valor !== null;
    }

    /** A cobrança desta empresa nasce no caixa.ia365 (cliente vinculado lá)? */
    public function vinculadaAoCaixa(): bool
    {
        return $this->caixa_cliente_id !== null;
    }

    /** Acesso suspenso por pendência financeira da plataforma? */
    public function estaSuspensa(): bool
    {
        return $this->cobranca_suspensa_em !== null;
    }

    /** Faturas da plataforma (IA365 → esta empresa). */
    public function plataformaFaturas(): HasMany
    {
        return $this->hasMany(PlataformaFatura::class)->orderByDesc('vencimento');
    }

    /** True se IA365 não cobra essa empresa (cortesia/parceiro/pós-pago). */
    public function ehGratuita(): bool
    {
        return $this->regime_cobranca?->ehGratuito() ?? false;
    }

    /* ------------------------------------------------------------------ */
    /*  Open Finance (17/11/2026)                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Quantas contas bancárias a empresa pode conectar: as do plano (se o plano tem a função)
     * mais as extras vendidas à parte. Cada conta custa para a IA365, então parceiro e
     * cortesia NÃO furam este limite — só o que está escrito aqui vale.
     */
    public function openFinanceLimiteContas(): int
    {
        $plano = $this->getPlanoAtivo();
        $doPlano = ($plano && $plano->open_finance_habilitado) ? (int) $plano->open_finance_contas : 0;

        return $doPlano + (int) $this->open_finance_contas_extras;
    }

    public function openFinanceLiberado(): bool
    {
        return $this->openFinanceLimiteContas() > 0;
    }

    /** Sem liberação a tela mostra o convite — a não ser que o admin tenha mandado esconder. */
    public function openFinanceVisivel(): bool
    {
        return $this->openFinanceLiberado() || ! $this->open_finance_ocultar;
    }

    public function openFinanceContasUsadas(): int
    {
        return ContaBancaria::withoutGlobalScopes()
            ->where('empresa_id', $this->id)
            ->whereNotNull('account_hash')
            ->where('situacao', '!=', 'desconectada')
            ->count();
    }

    public function openFinanceTemVaga(): bool
    {
        return $this->openFinanceContasUsadas() < $this->openFinanceLimiteContas();
    }

    /** True se essa empresa ignora limites/features do plano (parceiro). */
    public function bypassaLimitesPlano(): bool
    {
        return $this->regime_cobranca?->ignoraLimitesPlano() ?? false;
    }

    /**
     * Estende o trial em N dias (botão "+30 dias" do admin).
     *
     * Para empresas em regime gratuito (cortesia/parceiro/pós-pago), não
     * faz sentido — elas já não dependem do trial. Lança exceção pra falhar
     * cedo em vez de gravar estado confuso.
     *
     * Para empresas com assinatura paga ativa, estende a assinatura em vez
     * de regredir para trial (admin querendo "dar mais um tempo" a um
     * cliente pago não deveria mudar o status para trial).
     */
    public function estenderTrial(int $dias): void
    {
        if ($this->ehGratuita()) {
            throw new \DomainException(
                'Empresa em regime ' . $this->regime_cobranca->label()
                . ' não usa trial — já tem acesso liberado.'
            );
        }

        // Assinatura paga ativa? Estende a assinatura, não regride para trial.
        if ($this->assinatura_fim && $this->assinatura_fim->gte(Carbon::today())) {
            $this->assinatura_fim = $this->assinatura_fim->copy()->addDays($dias);
            $this->save();
            return;
        }

        $base = $this->trial_fim && $this->trial_fim->isFuture()
            ? $this->trial_fim
            : Carbon::today();
        $this->em_trial = true;
        $this->trial_inicio = $this->trial_inicio ?? Carbon::today();
        $this->trial_fim = $base->copy()->addDays($dias);
        $this->save();
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
     *
     * Parceiros estratégicos ignoram limites totalmente.
     */
    public function limiteAtingido(string $resource): bool
    {
        if ($this->bypassaLimitesPlano()) {
            return false;
        }

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
