<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $totalEmpresas  = Empresa::where('status', 'ativo')->count();
        $totalUnidades  = Unidade::where('status', 'ativa')->count();
        $totalUsuarios  = User::where('is_admin', false)->count();
        $empresasEmTrial = Empresa::where('em_trial', true)->count();

        $empresas = Empresa::latest()
            ->take(8)
            ->get();

        return view('admin.dashboard', compact(
            'totalEmpresas',
            'totalUnidades',
            'totalUsuarios',
            'empresasEmTrial',
            'empresas',
        ));
    }
}
