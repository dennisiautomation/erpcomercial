<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    public function index(Request $request)
    {
        $query = Categoria::with('parent:id,nome')->withCount('produtos');

        if ($request->filled('busca')) {
            $query->where('nome', 'like', "%{$request->busca}%");
        }

        $categorias = $query->orderBy('nome')->paginate(15)->withQueryString();

        return view('app.categorias.index', compact('categorias'));
    }

    public function create()
    {
        $pais = Categoria::whereNull('parent_id')
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get();

        return view('app.categorias.create', compact('pais'));
    }

    public function store(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $validated = $request->validate([
            'nome'      => [
                'required',
                'string',
                'max:255',
                Rule::unique('categorias')->where('empresa_id', $empresaId)->whereNull('deleted_at'),
            ],
            'descricao' => 'nullable|string',
            'parent_id' => 'nullable|exists:categorias,id',
        ]);

        $validated['status'] = 'ativo';

        Categoria::create($validated);

        return redirect()->route('app.categorias.index')
            ->with('success', 'Categoria cadastrada com sucesso!');
    }

    public function show(Categoria $categoria)
    {
        $categoria->load(['parent:id,nome', 'children:id,parent_id,nome,status', 'produtos' => function ($q) {
            $q->select('id', 'categoria_id', 'descricao', 'preco_venda', 'status')->limit(20);
        }]);

        return view('app.categorias.show', compact('categoria'));
    }

    public function edit(Categoria $categoria)
    {
        $pais = Categoria::whereNull('parent_id')
            ->where('status', 'ativo')
            ->where('id', '!=', $categoria->id)
            ->orderBy('nome')
            ->get();

        return view('app.categorias.edit', compact('categoria', 'pais'));
    }

    public function update(Request $request, Categoria $categoria)
    {
        $empresaId = auth()->user()->empresa_id;

        $validated = $request->validate([
            'nome'      => [
                'required',
                'string',
                'max:255',
                Rule::unique('categorias')->where('empresa_id', $empresaId)->whereNull('deleted_at')->ignore($categoria->id),
            ],
            'descricao' => 'nullable|string',
            'parent_id' => 'nullable|exists:categorias,id',
            'status'    => 'required|in:ativo,inativo',
        ]);

        $categoria->update($validated);

        return redirect()->route('app.categorias.index')
            ->with('success', 'Categoria atualizada com sucesso!');
    }

    public function destroy(Categoria $categoria)
    {
        if ($categoria->produtos()->exists()) {
            return redirect()->route('app.categorias.index')
                ->with('error', 'Não é possível excluir uma categoria que possui produtos vinculados.');
        }

        $categoria->delete();

        return redirect()->route('app.categorias.index')
            ->with('success', 'Categoria excluída com sucesso!');
    }
}
