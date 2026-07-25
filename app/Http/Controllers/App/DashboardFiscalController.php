<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusNotaFiscal;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Unidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Visão consolidada da saúde fiscal da empresa:
 *  - NF-es por status (últimos 30 dias)
 *  - Top 5 erros SEFAZ
 *  - Certificado A1 + dias para vencer
 *  - Webhooks ativos por unidade
 *  - Último backup XML
 *  - Status SEFAZ por UF (AJAX)
 */
class DashboardFiscalController extends Controller
{
    public function index(Request $request): View
    {
        $unidadeId = session('unidade_id');
        $empresaId = $request->user()->empresa_id;
        $desde = now()->subDays(30);

        // Por status
        $porStatus = NotaFiscal::query()
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->where('created_at', '>=', $desde)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // Top 5 erros
        $topErros = NotaFiscal::query()
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->where('status', StatusNotaFiscal::Rejeitada)
            ->where('created_at', '>=', $desde)
            ->whereNotNull('focus_mensagem')
            ->select('focus_mensagem', DB::raw('count(*) as total'))
            ->groupBy('focus_mensagem')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Por tipo
        $porTipo = NotaFiscal::query()
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->where('created_at', '>=', $desde)
            ->select('tipo', DB::raw('count(*) as total'))
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->all();

        // Por dia (últimos 14 dias) para o gráfico
        $serieDiaria = NotaFiscal::query()
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->where('created_at', '>=', now()->subDays(14))
            ->select(DB::raw('DATE(created_at) as dia'), DB::raw('count(*) as total'))
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        // Configurações por unidade
        $configs = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->with('unidade')
            ->get();

        // Último backup local
        $ultimosBackups = [];
        foreach ($configs as $config) {
            $cnpj = \App\Support\Cnpj::limpar($config->unidade->cnpj ?? '');
            if (! $cnpj) continue;
            $path = "fiscal/backups/{$cnpj}";
            if (Storage::disk('local')->exists($path)) {
                $files = collect(Storage::disk('local')->files($path))
                    ->sortDesc()
                    ->take(1)
                    ->values()
                    ->all();
                $ultimosBackups[$config->unidade_id] = $files[0] ?? null;
            }
        }

        return view('app.fiscal.dashboard', compact(
            'porStatus', 'topErros', 'porTipo', 'serieDiaria',
            'configs', 'ultimosBackups'
        ));
    }
}
