<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use Illuminate\Http\Request;

class ServicoController extends Controller
{
    public function index(Request $request)
    {
        $query = Servico::query();

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('descricao', 'like', "%{$busca}%")
                  ->orWhere('codigo', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $servicos = $query->orderBy('descricao')->paginate(15)->withQueryString();

        return view('app.servicos.index', compact('servicos'));
    }

    public function create()
    {
        return view('app.servicos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo'                    => 'nullable|string|max:20',
            'descricao'                 => 'required|string|max:255',
            'valor_padrao'              => 'required|numeric|min:0',
            'codigo_servico_municipal'  => 'nullable|string|max:20',
            'cnae'                      => 'nullable|string|max:10',
            'iss_aliquota'              => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['status'] = 'ativo';

        Servico::create($validated);

        return redirect()->route('app.servicos.index')
            ->with('success', 'Serviço cadastrado com sucesso!');
    }

    public function show(Servico $servico)
    {
        return view('app.servicos.show', compact('servico'));
    }

    public function edit(Servico $servico)
    {
        return view('app.servicos.edit', compact('servico'));
    }

    public function update(Request $request, Servico $servico)
    {
        $validated = $request->validate([
            'codigo'                    => 'nullable|string|max:20',
            'descricao'                 => 'required|string|max:255',
            'valor_padrao'              => 'required|numeric|min:0',
            'codigo_servico_municipal'  => 'nullable|string|max:20',
            'cnae'                      => 'nullable|string|max:10',
            'iss_aliquota'              => 'nullable|numeric|min:0|max:100',
            'status'                    => 'required|in:ativo,inativo',
        ]);

        $servico->update($validated);

        return redirect()->route('app.servicos.index')
            ->with('success', 'Serviço atualizado com sucesso!');
    }

    public function destroy(Servico $servico)
    {
        $servico->delete();

        return redirect()->route('app.servicos.index')
            ->with('success', 'Serviço excluído com sucesso!');
    }
}
