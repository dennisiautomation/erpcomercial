<?php

namespace App\Http\Controllers\App;

use App\Enums\TipoProduto;
use App\Http\Controllers\Controller;
use App\Models\GrupoOpcao;
use App\Models\Produto;
use App\Services\ComposicaoEstoque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * "Ficha técnica e opções" do produto (Fase 1 cafeteria, 30/10/2026).
 *
 * - Ficha técnica: produto Produzido (e Adicional) — insumos por unidade
 *   vendida. Salvar recalcula o custo do produto pela receita.
 * - Grupos de opções: produto que vai ao PDV (Revenda/Produzido) — tamanho,
 *   leite, extras que o caixa escolhe ao lançar.
 */
class ComposicaoProdutoController extends Controller
{
    public function edit(Produto $produto)
    {
        $this->exigirModulo();

        $produto->load(['fichaItens.insumo:id,descricao,unidade_medida,preco_custo,tipo', 'gruposOpcoes:id']);

        $insumos = Produto::whereIn('tipo', ComposicaoEstoque::tiposQueServemDeInsumo())
            ->where('status', 'ativo')
            ->where('id', '!=', $produto->id)
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'unidade_medida', 'preco_custo', 'tipo']);

        $grupos = GrupoOpcao::where('status', 'ativo')->with('itens.adicional:id,descricao,preco_venda')->orderBy('nome')->get();
        $custoFicha = ComposicaoEstoque::custoDaFicha($produto);

        return view('app.produtos.composicao', compact('produto', 'insumos', 'grupos', 'custoFicha'));
    }

    public function update(Request $request, Produto $produto)
    {
        $this->exigirModulo();
        $empresaId = $produto->empresa_id;
        $tipo = $produto->tipoEnum();

        $dados = $request->validate([
            'ficha'               => 'nullable|array|max:60',
            'ficha.*.insumo_id'   => [
                'nullable',
                'integer',
                'distinct',
                Rule::notIn([$produto->id]),
                Rule::exists('produtos', 'id')->where(fn ($q) => $q->where('empresa_id', $empresaId)
                    ->whereIn('tipo', ComposicaoEstoque::tiposQueServemDeInsumo())),
            ],
            'ficha.*.quantidade'  => 'nullable|numeric|min:0|max:99999',
            'grupos'              => 'nullable|array|max:20',
            'grupos.*'            => ['integer', Rule::exists('grupos_opcoes', 'id')->where('empresa_id', $empresaId)],
        ], [
            'ficha.*.insumo_id.exists'   => 'Um dos insumos não pode entrar na receita (só Insumo ou Revenda desta empresa).',
            'ficha.*.insumo_id.not_in'   => 'O produto não pode ser insumo da própria receita.',
            'ficha.*.insumo_id.distinct' => 'O mesmo insumo apareceu duas vezes na receita — some as quantidades numa linha só.',
        ]);

        $linhas = collect($dados['ficha'] ?? [])
            ->filter(fn ($l) => ! empty($l['insumo_id']) && (float) ($l['quantidade'] ?? 0) > 0)
            ->values();

        $temFicha = in_array($tipo, [TipoProduto::Produzido, TipoProduto::Adicional], true);
        $temGrupos = $tipo->vendeNoPdv();

        if ($tipo === TipoProduto::Produzido && $linhas->isEmpty()) {
            return back()->withInput()->withErrors([
                'ficha' => 'Produto Produzido precisa de pelo menos um insumo na receita — senão a venda não baixa nada do estoque.',
            ]);
        }

        DB::transaction(function () use ($produto, $linhas, $dados, $temFicha, $temGrupos) {
            if ($temFicha) {
                $produto->fichaItens()->delete();
                foreach ($linhas as $l) {
                    $produto->fichaItens()->create([
                        'empresa_id' => $produto->empresa_id,
                        'insumo_id'  => (int) $l['insumo_id'],
                        'quantidade' => round((float) $l['quantidade'], 4),
                    ]);
                }

                // Custo do produto = custo da receita (o markup acompanha)
                $custo = ComposicaoEstoque::custoDaFicha($produto);
                $produto->preco_custo = $custo;
                if ($custo > 0) {
                    $produto->markup = round(((float) $produto->preco_venda - $custo) / $custo * 100, 2);
                }
                $produto->save();
            }

            if ($temGrupos) {
                $sync = [];
                foreach (array_values($dados['grupos'] ?? []) as $ordem => $grupoId) {
                    $sync[(int) $grupoId] = ['ordem' => $ordem];
                }
                $produto->gruposOpcoes()->sync($sync);
            }
        });

        return redirect()->route('app.produtos.composicao', $produto)
            ->with('success', 'Ficha técnica e opções salvas.');
    }

    private function exigirModulo(): void
    {
        if (! auth()->user()->empresa?->usaFichaTecnica()) {
            abort(404);
        }
    }
}
