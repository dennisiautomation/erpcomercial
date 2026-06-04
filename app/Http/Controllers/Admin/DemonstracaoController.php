<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SolicitacaoDemonstracao;
use Illuminate\Http\Request;

/**
 * Listagem (admin IA365) dos leads capturados pela landing pública.
 */
class DemonstracaoController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $leads = SolicitacaoDemonstracao::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $contagem = [
            'novo'       => SolicitacaoDemonstracao::where('status', 'novo')->count(),
            'contatado'  => SolicitacaoDemonstracao::where('status', 'contatado')->count(),
            'convertido' => SolicitacaoDemonstracao::where('status', 'convertido')->count(),
            'descartado' => SolicitacaoDemonstracao::where('status', 'descartado')->count(),
        ];

        return view('admin.demonstracoes.index', compact('leads', 'contagem', 'status'));
    }

    public function updateStatus(Request $request, SolicitacaoDemonstracao $demonstracao)
    {
        $data = $request->validate([
            'status' => ['required', 'in:novo,contatado,convertido,descartado'],
        ]);

        $demonstracao->update($data);

        return back()->with('success', 'Status atualizado.');
    }
}
