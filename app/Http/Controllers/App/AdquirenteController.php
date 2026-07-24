<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AdquirenteTaxa;
use App\Models\ContaReceber;
use Illuminate\Http\Request;

class AdquirenteController extends Controller
{
    /** Cadastro de taxas/prazos das máquinas — CRUD numa página só. */
    public function index()
    {
        $taxas = AdquirenteTaxa::orderBy('nome')
            ->orderBy('forma')
            ->orderBy('parcelas_de')
            ->get();

        return view('app.adquirentes.index', compact('taxas'));
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        AdquirenteTaxa::create($dados + ['empresa_id' => session('empresa_id')]);

        return redirect()->route('app.adquirentes.index')
            ->with('success', 'Regra da máquina cadastrada!');
    }

    public function update(Request $request, AdquirenteTaxa $adquirente)
    {
        $adquirente->update($this->validar($request));

        return redirect()->route('app.adquirentes.index')
            ->with('success', 'Regra da máquina atualizada!');
    }

    public function destroy(AdquirenteTaxa $adquirente)
    {
        $adquirente->delete();

        return redirect()->route('app.adquirentes.index')
            ->with('success', 'Regra da máquina removida!');
    }

    /** Recebíveis de cartão: previsão de recebimento com taxa e líquido. */
    public function recebiveis(Request $request)
    {
        $query = ContaReceber::with(['venda', 'adquirenteTaxa'])
            ->whereNotNull('adquirente_taxa_id')
            ->orderBy('vencimento');

        if ($request->filled('data_inicio')) {
            $query->whereDate('vencimento', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->whereDate('vencimento', '<=', $request->data_fim);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $contas = $query->paginate(30)->withQueryString();

        $totais = [
            'bruto'   => (clone $query)->sum('valor'),
            'liquido' => (clone $query)->sum('valor_liquido'),
        ];
        $totais['taxas'] = round($totais['bruto'] - $totais['liquido'], 2);

        return view('app.adquirentes.recebiveis', compact('contas', 'totais'));
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'nome'            => 'required|string|max:80',
            'forma'           => 'required|in:cartao_debito,cartao_credito',
            'parcelas_de'     => 'required|integer|min:1|max:24',
            'parcelas_ate'    => 'required|integer|min:1|max:24|gte:parcelas_de',
            'taxa_percentual' => 'required|numeric|min:0|max:100',
            'prazo_dias'      => 'required|integer|min:0|max:365',
            'ativo'           => 'nullable|boolean',
        ]);

        $dados['ativo'] = (bool) ($dados['ativo'] ?? false);

        return $dados;
    }
}
