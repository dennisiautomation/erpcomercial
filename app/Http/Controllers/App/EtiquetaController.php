<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoLoja;
use App\Models\Produto;
use App\Services\TabelaPrecoService;
use Illuminate\Http\Request;

class EtiquetaController extends Controller
{
    /**
     * Show label generation page with product selection.
     */
    public function index(Request $request)
    {
        $produtos = Produto::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativo')
            ->orderBy('descricao')
            ->get();

        return view('app.etiquetas.index', compact('produtos'));
    }

    /**
     * Generate printable labels.
     */
    public function gerar(Request $request, TabelaPrecoService $tabelaPrecos)
    {
        $request->validate([
            'produtos' => 'required|array|min:1',
            'produtos.*.id' => 'required|exists:produtos,id',
            'produtos.*.quantidade' => 'required|integer|min:1|max:100',
            'formato' => 'required|in:2x5,3x7,4x10,termica-40x25,termica-50x30,termica-60x40,termica-33x22',
        ]);

        $configLoja = ConfiguracaoLoja::daUnidade();
        $maxParcelas = max(1, (int) ($configLoja->max_parcelas ?? 6));

        $itens = [];
        $precosEtiqueta = [];
        foreach ($request->produtos as $item) {
            $produto = Produto::with('precos')->find($item['id']);

            // Etiqueta dupla ("6x R$ X ou R$ Y no PIX") só quando a tabela
            // crédito difere da base — sem configuração, etiqueta fica como antes.
            if (! isset($precosEtiqueta[$produto->id])) {
                $precos = $tabelaPrecos->precosDoProduto($produto, $configLoja);
                $precosEtiqueta[$produto->id] = [
                    'dual'          => $precos['credito'] > $precos['dinheiro_pix'],
                    'base'          => $precos['dinheiro_pix'],
                    'credito'       => $precos['credito'],
                    'parcelas'      => $maxParcelas,
                    'parcela_valor' => round($precos['credito'] / $maxParcelas, 2),
                ];
            }

            for ($i = 0; $i < $item['quantidade']; $i++) {
                $itens[] = $produto;
            }
        }

        $formato = $request->formato;

        return view('app.etiquetas.print', compact('itens', 'formato', 'precosEtiqueta'));
    }
}
