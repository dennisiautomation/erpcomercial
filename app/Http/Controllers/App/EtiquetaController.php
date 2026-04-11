<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Produto;
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
    public function gerar(Request $request)
    {
        $request->validate([
            'produtos' => 'required|array|min:1',
            'produtos.*.id' => 'required|exists:produtos,id',
            'produtos.*.quantidade' => 'required|integer|min:1|max:100',
            'formato' => 'required|in:2x5,3x7,4x10',
        ]);

        $itens = [];
        foreach ($request->produtos as $item) {
            $produto = Produto::find($item['id']);
            for ($i = 0; $i < $item['quantidade']; $i++) {
                $itens[] = $produto;
            }
        }

        $formato = $request->formato;

        return view('app.etiquetas.print', compact('itens', 'formato'));
    }
}
