<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CentroCusto;
use Illuminate\Http\Request;

class CentroCustoController extends Controller
{
    public function index()
    {
        $empresaId = auth()->user()->empresa_id;

        $centros = CentroCusto::where('empresa_id', $empresaId)
            ->orderBy('codigo')
            ->paginate(20);

        return view('app.centros-custo.index', compact('centros'));
    }

    public function create()
    {
        return view('app.centros-custo.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo'    => 'required|string|max:20',
            'nome'      => 'required|string|max:255',
            'descricao' => 'nullable|string|max:500',
        ]);

        $empresaId = auth()->user()->empresa_id;

        $exists = CentroCusto::where('empresa_id', $empresaId)
            ->where('codigo', $validated['codigo'])
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['codigo' => 'Este codigo ja existe para esta empresa.']);
        }

        CentroCusto::create([
            'empresa_id' => $empresaId,
            'codigo'     => $validated['codigo'],
            'nome'       => $validated['nome'],
            'descricao'  => $validated['descricao'] ?? null,
        ]);

        return redirect()->route('app.centros-custo.index')
            ->with('success', 'Centro de custo cadastrado com sucesso!');
    }

    public function edit(CentroCusto $centrosCusto)
    {
        return view('app.centros-custo.edit', compact('centrosCusto'));
    }

    public function update(Request $request, CentroCusto $centrosCusto)
    {
        $validated = $request->validate([
            'codigo'    => 'required|string|max:20',
            'nome'      => 'required|string|max:255',
            'descricao' => 'nullable|string|max:500',
            'ativo'     => 'nullable|boolean',
        ]);

        $empresaId = auth()->user()->empresa_id;

        $exists = CentroCusto::where('empresa_id', $empresaId)
            ->where('codigo', $validated['codigo'])
            ->where('id', '!=', $centrosCusto->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['codigo' => 'Este codigo ja existe para esta empresa.']);
        }

        $centrosCusto->update([
            'codigo'    => $validated['codigo'],
            'nome'      => $validated['nome'],
            'descricao' => $validated['descricao'] ?? null,
            'ativo'     => $request->boolean('ativo', true),
        ]);

        return redirect()->route('app.centros-custo.index')
            ->with('success', 'Centro de custo atualizado com sucesso!');
    }

    public function destroy(CentroCusto $centrosCusto)
    {
        if ($centrosCusto->contasReceber()->exists() || $centrosCusto->contasPagar()->exists()) {
            return back()->with('error', 'Nao e possivel excluir centro de custo com movimentacoes vinculadas.');
        }

        $centrosCusto->delete();

        return redirect()->route('app.centros-custo.index')
            ->with('success', 'Centro de custo excluido com sucesso!');
    }
}
