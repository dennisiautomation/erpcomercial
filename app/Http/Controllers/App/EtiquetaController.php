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
        // Formato compacto do form: produtos[<id>] = quantidade (1 input por produto).
        // O formato antigo produtos[i][id]/[quantidade] gastava 2 inputs por produto e
        // estourava o max_input_vars do PHP com "Selecionar Todos" (515 produtos = 1030
        // campos, PHP corta em 1000) — o corte silencioso chegava aqui como
        // "validation.required" sem culpado aparente.
        // Sem regra em produtos.*: o valor pode ser int (formato novo) OU array
        // {id, quantidade} (abas abertas antes do deploy) — a normalização abaixo
        // aceita os dois e a quantidade é clampada em 1..100.
        $request->validate([
            'produtos' => 'required|array|min:1',
            'formato'  => 'required|in:2x5,3x7,4x10,termica-40x25,termica-50x30,termica-60x40,termica-33x22,termica-36x20-2col,termica-tag-35x60',
        ]);

        // Compat: aceita também o formato antigo produtos[][id]/[quantidade]
        $selecao = [];
        foreach ($request->produtos as $chave => $valor) {
            if (is_array($valor)) {
                $selecao[(int) ($valor['id'] ?? 0)] = (int) ($valor['quantidade'] ?? 1);
            } else {
                $selecao[(int) $chave] = (int) $valor;
            }
        }
        unset($selecao[0]);

        $validos = Produto::whereIn('id', array_keys($selecao))->pluck('id')->all();
        if ($validos === []) {
            return back()->with('error', 'Selecione ao menos um produto válido.');
        }

        $configLoja = ConfiguracaoLoja::daUnidade();
        $maxParcelas = max(1, (int) ($configLoja->max_parcelas ?? 6));

        $produtosMap = Produto::with('precos')->whereIn('id', $validos)->get()->keyBy('id');

        $itens = [];
        $precosEtiqueta = [];
        foreach ($selecao as $produtoId => $quantidade) {
            $produto = $produtosMap[$produtoId] ?? null;
            if (! $produto) {
                continue;
            }
            $quantidade = max(1, min(100, $quantidade));

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

            for ($i = 0; $i < $quantidade; $i++) {
                $itens[] = $produto;
            }
        }

        $formato = $request->formato;

        return view('app.etiquetas.print', compact('itens', 'formato', 'precosEtiqueta'));
    }
}
