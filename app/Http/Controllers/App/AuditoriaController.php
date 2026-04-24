<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = session('empresa_id');

        $query = Activity::query()
            ->with('causer')
            ->where(function ($q) use ($empresaId) {
                // Multi-tenant: só eventos desta empresa (via properties) ou sem tenant (system)
                $q->where('properties->empresa_id', $empresaId)
                    ->orWhereNull('properties->empresa_id');
            })
            ->latest();

        if ($tipo = $request->input('tipo')) {
            $query->where('log_name', $tipo);
        }

        if ($evento = $request->input('evento')) {
            $query->where('event', $evento);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('causer_id', $userId)->where('causer_type', \App\Models\User::class);
        }

        if ($desde = $request->input('desde')) {
            $query->where('created_at', '>=', $desde . ' 00:00:00');
        }

        if ($ate = $request->input('ate')) {
            $query->where('created_at', '<=', $ate . ' 23:59:59');
        }

        $activities = $query->paginate(30)->withQueryString();

        $tiposDisponiveis = Activity::query()
            ->where(function ($q) use ($empresaId) {
                $q->where('properties->empresa_id', $empresaId)
                    ->orWhereNull('properties->empresa_id');
            })
            ->distinct()
            ->pluck('log_name')
            ->filter()
            ->values();

        $usuarios = \App\Models\User::where('empresa_id', $empresaId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('app.auditoria.index', compact('activities', 'tiposDisponiveis', 'usuarios'));
    }
}
