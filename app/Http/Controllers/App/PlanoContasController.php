<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\PlanoContas;
use Illuminate\Http\Request;

class PlanoContasController extends Controller
{
    public function index()
    {
        $empresaId = auth()->user()->empresa_id;

        $contas = PlanoContas::where('empresa_id', $empresaId)
            ->whereNull('parent_id')
            ->with('children.children.children')
            ->orderBy('codigo')
            ->get();

        return view('app.plano-contas.index', compact('contas'));
    }

    public function create(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $parentId = $request->query('parent_id');

        $contasPai = PlanoContas::where('empresa_id', $empresaId)
            ->where('natureza', 'sintetica')
            ->orderBy('codigo')
            ->get();

        return view('app.plano-contas.create', compact('contasPai', 'parentId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:plano_contas,id',
            'codigo'    => 'required|string|max:20',
            'nome'      => 'required|string|max:255',
            'tipo'      => 'required|in:receita,despesa,custo',
            'natureza'  => 'required|in:sintetica,analitica',
        ]);

        $empresaId = auth()->user()->empresa_id;

        // Check unique code per empresa
        $exists = PlanoContas::where('empresa_id', $empresaId)
            ->where('codigo', $validated['codigo'])
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['codigo' => 'Este codigo ja existe para esta empresa.']);
        }

        PlanoContas::create([
            'empresa_id' => $empresaId,
            'parent_id'  => $validated['parent_id'],
            'codigo'     => $validated['codigo'],
            'nome'       => $validated['nome'],
            'tipo'       => $validated['tipo'],
            'natureza'   => $validated['natureza'],
        ]);

        return redirect()->route('app.plano-contas.index')
            ->with('success', 'Conta cadastrada com sucesso!');
    }

    public function edit(PlanoContas $planoContas)
    {
        $empresaId = auth()->user()->empresa_id;

        $contasPai = PlanoContas::where('empresa_id', $empresaId)
            ->where('natureza', 'sintetica')
            ->where('id', '!=', $planoContas->id)
            ->orderBy('codigo')
            ->get();

        return view('app.plano-contas.edit', compact('planoContas', 'contasPai'));
    }

    public function update(Request $request, PlanoContas $planoContas)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:plano_contas,id',
            'codigo'    => 'required|string|max:20',
            'nome'      => 'required|string|max:255',
            'tipo'      => 'required|in:receita,despesa,custo',
            'natureza'  => 'required|in:sintetica,analitica',
            'ativo'     => 'nullable|boolean',
        ]);

        $empresaId = auth()->user()->empresa_id;

        // Check unique code per empresa (excluding self)
        $exists = PlanoContas::where('empresa_id', $empresaId)
            ->where('codigo', $validated['codigo'])
            ->where('id', '!=', $planoContas->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['codigo' => 'Este codigo ja existe para esta empresa.']);
        }

        $planoContas->update([
            'parent_id' => $validated['parent_id'],
            'codigo'    => $validated['codigo'],
            'nome'      => $validated['nome'],
            'tipo'      => $validated['tipo'],
            'natureza'  => $validated['natureza'],
            'ativo'     => $request->boolean('ativo', true),
        ]);

        return redirect()->route('app.plano-contas.index')
            ->with('success', 'Conta atualizada com sucesso!');
    }

    public function destroy(PlanoContas $planoContas)
    {
        // Check if has children
        if ($planoContas->children()->exists()) {
            return back()->with('error', 'Nao e possivel excluir conta com subcontas vinculadas.');
        }

        // Check if has movements
        if ($planoContas->contasReceber()->exists() || $planoContas->contasPagar()->exists()) {
            return back()->with('error', 'Nao e possivel excluir conta com movimentacoes vinculadas.');
        }

        $planoContas->delete();

        return redirect()->route('app.plano-contas.index')
            ->with('success', 'Conta excluida com sucesso!');
    }
}
