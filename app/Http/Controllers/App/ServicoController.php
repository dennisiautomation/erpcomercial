<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\Servico;
use Illuminate\Http\Request;

class ServicoController extends Controller
{
    public function index(Request $request)
    {
        $query = Servico::where('empresa_id', session('empresa_id'));

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('descricao', 'like', "%{$busca}%")
                  ->orWhere('codigo_lc116', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $servicos = $query->orderBy('descricao')->paginate(20)->withQueryString();

        $totalAtivos = Servico::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->count();
        $totalInativos = Servico::where('empresa_id', session('empresa_id'))->where('status', 'inativo')->count();

        return view('app.servicos.index', compact('servicos', 'totalAtivos', 'totalInativos'));
    }

    public function create()
    {
        $emiteNfse = ConfiguracaoFiscal::where('unidade_id', session('unidade_id'))
            ->where('emissao_fiscal_ativa', true)
            ->exists();

        return view('app.servicos.create', compact('emiteNfse'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_lc116'                    => 'nullable|string|max:20',
            'descricao'                 => 'required|string|max:255',
            'valor_padrao'              => 'required|numeric|min:0',
            'codigo_lc116'  => 'nullable|string|max:20',
            'cnae'                      => 'nullable|string|max:10',
            'iss_aliquota'              => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['empresa_id'] = session('empresa_id');
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
        $emiteNfse = ConfiguracaoFiscal::where('unidade_id', session('unidade_id'))
            ->where('emissao_fiscal_ativa', true)
            ->exists();

        return view('app.servicos.edit', compact('servico', 'emiteNfse'));
    }

    public function update(Request $request, Servico $servico)
    {
        $validated = $request->validate([
            'codigo_lc116'                    => 'nullable|string|max:20',
            'descricao'                 => 'required|string|max:255',
            'valor_padrao'              => 'required|numeric|min:0',
            'codigo_lc116'  => 'nullable|string|max:20',
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
