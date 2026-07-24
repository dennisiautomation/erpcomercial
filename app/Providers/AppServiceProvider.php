<?php

namespace App\Providers;

use App\Models\VendaItem;
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

        // Enriquecer toda Activity com empresa_id do subject (multi-tenant)
        Activity::creating(function (Activity $activity) {
            if ($activity->subject && isset($activity->subject->empresa_id)) {
                $props = $activity->properties ?? collect();
                if (is_array($props)) {
                    $props = collect($props);
                }
                if (! $props->has('empresa_id')) {
                    $activity->properties = $props->put('empresa_id', $activity->subject->empresa_id);
                }
            }
        });
    }
}
