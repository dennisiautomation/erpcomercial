<?php

namespace App\Providers;

use App\Models\AgenteIaConfig;
use App\Models\EmpresaGateway;
use App\Models\Produto;
use App\Models\VendaItem;
use App\Observers\AgenteIaConfigObserver;
use App\Observers\EmpresaGatewayObserver;
use App\Observers\ProdutoObserver;
use App\Observers\VendaItemObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Traduções em resources/lang (o diretório lang/ na raiz não existe)
        $this->app->useLangPath(resource_path('lang'));
    }

    public function boot(): void
    {
        // Paginação com markup Bootstrap 5 (a view padrão do Laravel é
        // Tailwind — sem Tailwind na página, as setas SVG saem gigantes)
        Paginator::useBootstrapFive();

        // App POS Stone (28/10/2026): limitadores NOMEADOS. `throttle:20,1` sem nome usa a
        // mesma chave (IP) para todas as rotas — a loja com várias maquininhas atrás do
        // mesmo IP travaria o login depois de 20 requisições quaisquer no minuto.
        RateLimiter::for('pos', fn (Request $r) => Limit::perMinute(300)
            ->by('pos:' . ($r->bearerToken() ? hash('sha256', $r->bearerToken()) : $r->ip())));
        RateLimiter::for('pos-login', fn (Request $r) => [
            Limit::perMinute(10)->by('pos-login:' . strtolower((string) $r->input('email')) . '|' . $r->ip()),
            Limit::perMinute(60)->by('pos-login-ip:' . $r->ip()),
        ]);

        // Snapshot fiscal automático: copia NCM/CFOP/CST/alíquotas do produto
        // para o venda_item na criação. Imuniza histórico contra edições do produto.
        VendaItem::observe(VendaItemObserver::class);

        // Agente IA: produto alterado → re-indexa no banco vetorial (só
        // dispara job para empresas com o módulo ativo — ver o Observer)
        Produto::observe(ProdutoObserver::class);

        // Integração ligada/desligada (Uber, Melhor Envio, PIX, Asaas) ou Agente
        // IA ativado/desativado → aviso assinado ao app.ia365, que re-lê
        // /capacidades e atualiza o agente sozinho (08/09/2026).
        EmpresaGateway::observe(EmpresaGatewayObserver::class);
        AgenteIaConfig::observe(AgenteIaConfigObserver::class);

        // Enriquecer toda Activity com empresa_id do subject (multi-tenant)
        Activity::creating(function (Activity $activity) {
            $props = $activity->properties ?? collect();
            if (is_array($props)) {
                $props = collect($props);
            }

            if ($activity->subject && isset($activity->subject->empresa_id)
                && ! $props->has('empresa_id')) {
                $props = $props->put('empresa_id', $activity->subject->empresa_id);
            }

            // Sessão "acessar como": tudo que o admin fizer logado como o
            // cliente fica marcado com o id do admin real (rastro de auditoria)
            if (session()->has('acesso_como_admin_id')) {
                $props = $props->put('acesso_como_admin_id', session('acesso_como_admin_id'));
            }

            if ($props->isNotEmpty()) {
                $activity->properties = $props;
            }
        });
    }
}
