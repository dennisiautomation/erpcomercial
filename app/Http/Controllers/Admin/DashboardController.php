<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\NotaFiscal;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $stats = [
            'empresas_ativas'  => Empresa::where('status', 'ativo')->count(),
            'total_unidades'   => Unidade::count(),
            'usuarios_ativos'  => User::where('status', 'ativo')->count(),
            'notas_mes'        => NotaFiscal::whereMonth('created_at', now()->month)
                                    ->whereYear('created_at', now()->year)
                                    ->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
