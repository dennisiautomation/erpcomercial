<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Produto;
use App\Services\FiscalAutoConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $query = Produto::with('categoria:id,nome');

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('descricao', 'like', "%{$busca}%")
                  ->orWhere('codigo_barras', 'like', "%{$busca}%")
                  ->orWhere('sku', 'like', "%{$busca}%")
                  ->orWhere('codigo_interno', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $produtos = $query->orderBy('descricao')->paginate(15)->withQueryString();
        $categorias = Categoria::where('status', 'ativo')->orderBy('nome')->get();

        return view('app.produtos.index', compact('produtos', 'categorias'));
    }

    public function create()
    {
        $empresa = auth()->user()->empresa;
        $regime = $empresa->regime_tributario instanceof \App\Enums\RegimeTributario
            ? $empresa->regime_tributario->value
            : $empresa->regime_tributario;

        $fiscalDefaults = FiscalAutoConfig::defaults($regime);
        $cfopOptions = FiscalAutoConfig::cfopOptions();
        $origemOptions = FiscalAutoConfig::origemOptions();
        $categorias = Categoria::where('empresa_id', $empresa->id)->orderBy('nome')->get();

        // Config fiscal da unidade ativa — usada para decidir se mostra campos da Reforma Tributária
        $configFiscal = \App\Models\ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->where('unidade_id', session('unidade_id'))
            ->first();

        return view('app.produtos.create', compact('categorias', 'fiscalDefaults', 'cfopOptions', 'origemOptions', 'configFiscal'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_barras'      => 'nullable|string|max:50',
            'sku'                => 'nullable|string|max:50',
            'descricao'          => 'required|string|max:255',
            'descricao_detalhada'=> 'nullable|string',
            'unidade_medida'     => 'required|in:UN,KG,CX,PCT,LT,MT,M2,M3,PAR,JG',
            'categoria_id'       => 'nullable|exists:categorias,id',
            'ncm'                => 'nullable|string|max:10',
            'cest'               => 'nullable|string|max:10',
            'origem'             => 'nullable|string|max:1',
            'preco_custo'        => 'nullable|numeric|min:0',
            'markup'             => 'nullable|numeric|min:0',
            'preco_venda'        => 'required|numeric|min:0',
            'estoque_minimo'     => 'nullable|numeric|min:0',
            'foto'               => 'nullable|image|max:2048',
            'peso_bruto'         => 'nullable|numeric|min:0',
            'peso_liquido'       => 'nullable|numeric|min:0',
            'cfop'               => 'nullable|string|max:10',
            'cst_csosn'          => 'nullable|string|max:10',
            'icms_aliquota'      => 'nullable|numeric|min:0|max:100',
            'pis_aliquota'       => 'nullable|numeric|min:0|max:100',
            'cofins_aliquota'    => 'nullable|numeric|min:0|max:100',
            'ipi_aliquota'       => 'nullable|numeric|min:0|max:100',
            // Reforma Tributária
            'ibs_aliquota'       => 'nullable|numeric|min:0|max:100',
            'cbs_aliquota'       => 'nullable|numeric|min:0|max:100',
            'is_aliquota'        => 'nullable|numeric|min:0|max:100',
            'cst_ibs_cbs'        => 'nullable|string|max:3',
            'classificacao_ibs'  => 'nullable|string|max:10',
            // Declaração de Importação
            'di_numero'              => 'nullable|string|max:20',
            'di_data'                => 'nullable|date',
            'di_local_desembaraco'   => 'nullable|string|max:100',
            'di_uf_desembaraco'      => 'nullable|string|size:2',
            'di_data_desembaraco'    => 'nullable|date',
            'di_via_transp'          => 'nullable|integer|min:1|max:12',
            'di_valor_afrmm'         => 'nullable|numeric|min:0',
            'di_forma_importacao'    => 'nullable|integer|in:1,2,3',
            'di_adicao_numero'       => 'nullable|string|max:10',
        ]);

        // Fill empty fiscal fields with defaults based on regime tributario
        if (empty($validated['cst_csosn'] ?? null)) {
            $regime = auth()->user()->empresa->regime_tributario;
            $regimeValue = $regime instanceof \App\Enums\RegimeTributario ? $regime->value : $regime;
            $defaults = FiscalAutoConfig::defaults($regimeValue);
            $validated['cst_csosn'] = $defaults['cst_csosn'];
            $validated['cfop'] = $validated['cfop'] ?? $defaults['cfop_venda_interna'];
            $validated['icms_aliquota'] = $validated['icms_aliquota'] ?? $defaults['icms_aliquota'];
            $validated['pis_aliquota'] = $validated['pis_aliquota'] ?? $defaults['pis_aliquota'];
            $validated['cofins_aliquota'] = $validated['cofins_aliquota'] ?? $defaults['cofins_aliquota'];
            $validated['origem'] = $validated['origem'] ?? $defaults['origem'];
        }

        // Auto-generate codigo_interno
        $empresaId = auth()->user()->empresa_id;
        $ultimo = Produto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->max('codigo_interno');
        $proximo = $ultimo ? intval($ultimo) + 1 : 1;
        $validated['codigo_interno'] = str_pad($proximo, 6, '0', STR_PAD_LEFT);
        $validated['status'] = 'ativo';

        // Handle foto upload
        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('produtos', 'public');
        }

        Produto::create($validated);

        return redirect()->route('app.produtos.index')
            ->with('success', 'Produto cadastrado com sucesso!');
    }

    public function show(Produto $produto)
    {
        $produto->load(['categoria:id,nome', 'estoqueMovimentacoes' => function ($q) {
            $q->latest()->limit(20);
        }]);

        return view('app.produtos.show', compact('produto'));
    }

    public function edit(Produto $produto)
    {
        $empresa = auth()->user()->empresa;
        $regime = $empresa->regime_tributario instanceof \App\Enums\RegimeTributario
            ? $empresa->regime_tributario->value
            : $empresa->regime_tributario;

        $fiscalDefaults = FiscalAutoConfig::defaults($regime);
        $cfopOptions = FiscalAutoConfig::cfopOptions();
        $origemOptions = FiscalAutoConfig::origemOptions();
        $categorias = Categoria::where('empresa_id', $empresa->id)->orderBy('nome')->get();

        $configFiscal = \App\Models\ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->where('unidade_id', session('unidade_id'))
            ->first();

        return view('app.produtos.edit', compact('produto', 'categorias', 'fiscalDefaults', 'cfopOptions', 'origemOptions', 'configFiscal'));
    }

    public function update(Request $request, Produto $produto)
    {
        $validated = $request->validate([
            'codigo_barras'      => 'nullable|string|max:50',
            'sku'                => 'nullable|string|max:50',
            'descricao'          => 'required|string|max:255',
            'descricao_detalhada'=> 'nullable|string',
            'unidade_medida'     => 'required|in:UN,KG,CX,PCT,LT,MT,M2,M3,PAR,JG',
            'categoria_id'       => 'nullable|exists:categorias,id',
            'ncm'                => 'nullable|string|max:10',
            'cest'               => 'nullable|string|max:10',
            'origem'             => 'nullable|string|max:1',
            'preco_custo'        => 'nullable|numeric|min:0',
            'markup'             => 'nullable|numeric|min:0',
            'preco_venda'        => 'required|numeric|min:0',
            'estoque_minimo'     => 'nullable|numeric|min:0',
            'foto'               => 'nullable|image|max:2048',
            'peso_bruto'         => 'nullable|numeric|min:0',
            'peso_liquido'       => 'nullable|numeric|min:0',
            'cfop'               => 'nullable|string|max:10',
            'cst_csosn'          => 'nullable|string|max:10',
            'icms_aliquota'      => 'nullable|numeric|min:0|max:100',
            'pis_aliquota'       => 'nullable|numeric|min:0|max:100',
            'cofins_aliquota'    => 'nullable|numeric|min:0|max:100',
            'ipi_aliquota'       => 'nullable|numeric|min:0|max:100',
            // Reforma Tributária
            'ibs_aliquota'       => 'nullable|numeric|min:0|max:100',
            'cbs_aliquota'       => 'nullable|numeric|min:0|max:100',
            'is_aliquota'        => 'nullable|numeric|min:0|max:100',
            'cst_ibs_cbs'        => 'nullable|string|max:3',
            'classificacao_ibs'  => 'nullable|string|max:10',
            // Declaração de Importação
            'di_numero'              => 'nullable|string|max:20',
            'di_data'                => 'nullable|date',
            'di_local_desembaraco'   => 'nullable|string|max:100',
            'di_uf_desembaraco'      => 'nullable|string|size:2',
            'di_data_desembaraco'    => 'nullable|date',
            'di_via_transp'          => 'nullable|integer|min:1|max:12',
            'di_valor_afrmm'         => 'nullable|numeric|min:0',
            'di_forma_importacao'    => 'nullable|integer|in:1,2,3',
            'di_adicao_numero'       => 'nullable|string|max:10',
            'status'             => 'required|in:ativo,inativo',
        ]);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            // Delete old foto if exists
            if ($produto->foto) {
                Storage::disk('public')->delete($produto->foto);
            }
            $validated['foto'] = $request->file('foto')->store('produtos', 'public');
        }

        $produto->update($validated);

        return redirect()->route('app.produtos.index')
            ->with('success', 'Produto atualizado com sucesso!');
    }

    public function destroy(Produto $produto)
    {
        if ($produto->foto) {
            Storage::disk('public')->delete($produto->foto);
        }

        $produto->delete();

        return redirect()->route('app.produtos.index')
            ->with('success', 'Produto excluido com sucesso!');
    }
}
