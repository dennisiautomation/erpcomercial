<?php

namespace App\Providers;

use App\Models\Produto;
use App\Models\VendaItem;
use App\Observers\ProdutoObserver;
use App\Observers\VendaItemObserver;
use Illuminate\Pagination\Paginator;
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

        // Snapshot fiscal automático: copia NCM/CFOP/CST/alíquotas do produto
        // para o venda_item na criação. Imuniza histórico contra edições do produto.
        VendaItem::observe(VendaItemObserver::class);

        // Agente IA: produto alterado → re-indexa no banco vetorial (só
        // dispara job para empresas com o módulo ativo — ver o Observer)
        Produto::observe(ProdutoObserver::class);

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
