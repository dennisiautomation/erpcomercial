<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoLoja;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\Unidade;
use App\Services\PromocaoService;
use App\Services\TabelaPrecoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Promoções por período (05/11/2026): assistente em 4 passos (período → lojas →
 * produtos → preços) + revisão; duplicar uma promoção (a nova já vem com a lista de
 * produtos e preços); a tela nova sugere a lista da última promoção.
 * Preço: PromocaoService + TabelaPrecoService::precosDoProduto.
 */
class PromocaoController extends Controller
{
    public function index(Request $request)
    {
        $filtro = (string) $request->query('status', '');

        $promocoes = Promocao::query()
            ->with(['unidades' => fn ($q) => $q->withoutGlobalScopes()])
            ->withCount('itens')
            ->orderByDesc('data_inicio')
            ->orderByDesc('id')
            ->get();

        if ($filtro !== '') {
            $promocoes = $promocoes->filter(fn (Promocao $p) => $p->status() === $filtro)->values();
        }

        return view('app.promocoes.index', compact('promocoes', 'filtro'));
    }

    public function create(Request $request, TabelaPrecoService $tabela)
    {
        $lojas = $this->lojasDoUsuario();
        $base = null;

        if ($request->filled('duplicar')) {
            $base = Promocao::with(['itens.produto', 'unidades' => fn ($q) => $q->withoutGlobalScopes()])
                ->findOrFail((int) $request->query('duplicar'));
        }

        // Sugestão: a lista da última promoção criada (05/11 — "já sugere a lista anterior")
        $ultima = $base ? null : Promocao::withCount('itens')->latest('id')->first();

        $inicial = [
            'nome' => $base ? $base->nome : '',
            'data_inicio' => '',
            'data_fim' => '',
            'unidades' => $base ? $base->unidades->pluck('id')->intersect($lojas->pluck('id'))->values()->all() : [],
            'itens' => $base ? $this->itensParaTela($base, $tabela) : [],
            'origem_id' => $base?->id,
        ];

        return view('app.promocoes.create', compact('lojas', 'inicial', 'base', 'ultima'));
    }

    /** Lista de produtos e preços de uma promoção, para o assistente (sugestão da última). */
    public function itens(Promocao $promocao, TabelaPrecoService $tabela): JsonResponse
    {
        $promocao->load('itens.produto');

        return response()->json(['nome' => $promocao->nome, 'itens' => $this->itensParaTela($promocao, $tabela)]);
    }

    /** Busca de produto do passo 3 (mesma regra do PDV) com o preço à vista SEM promoção. */
    public function buscarProdutos(Request $request, TabelaPrecoService $tabela): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));
        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $config = ConfiguracaoLoja::daUnidade();
        $produtos = \App\Http\Controllers\App\PdvController::buscarProdutosDaEmpresa((int) session('empresa_id'), $termo, 50);

        return response()->json($produtos->map(fn ($p) => [
            'produto_id' => $p->id,
            'descricao' => $p->descricao,
            'codigo' => $p->codigo_barras ?: ($p->codigo_interno ?: $p->sku),
            'preco_referencia' => $tabela->precosSemPromocao($p, $config)['dinheiro_pix'],
        ])->values());
    }

    public function store(Request $request, PromocaoService $promocoes, TabelaPrecoService $tabela)
    {
        $empresaId = (int) session('empresa_id');
        $lojasPermitidas = $this->lojasDoUsuario()->pluck('id')->all();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'data_inicio' => ['required', 'date', 'after_or_equal:today'],
            'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
            'unidades' => ['required', 'array', 'min:1'],
            'unidades.*' => ['integer', 'in:' . implode(',', $lojasPermitidas ?: [0])],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'integer', 'distinct'],
            'itens.*.tipo' => ['required', 'in:preco,percentual'],
            'itens.*.valor' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'origem_id' => ['nullable', 'integer'],
        ], [
            'data_inicio.after_or_equal' => 'A promoção não pode começar antes de hoje.',
            'data_fim.after_or_equal' => 'O fim tem que ser no mesmo dia do início ou depois.',
            'unidades.required' => 'Escolha pelo menos uma loja.',
            'unidades.*.in' => 'Loja fora das suas lojas.',
            'itens.required' => 'Adicione pelo menos um produto.',
            'itens.*.produto_id.distinct' => 'Um produto apareceu duas vezes na lista.',
            'itens.*.valor.gt' => 'Todo produto precisa de preço ou % maior que zero.',
        ], [
            'data_inicio' => 'início', 'data_fim' => 'fim',
        ]);

        $erros = [];
        $produtos = Produto::where('empresa_id', $empresaId)
            ->whereIn('id', collect($dados['itens'])->pluck('produto_id'))
            ->get()->keyBy('id');
        $config = ConfiguracaoLoja::daUnidade();

        foreach ($dados['itens'] as $i => $item) {
            $produto = $produtos->get((int) $item['produto_id']);
            if (! $produto) {
                $erros["itens.{$i}.produto_id"] = 'Produto não encontrado.';
                continue;
            }
            if ($item['tipo'] === 'percentual' && (float) $item['valor'] >= 100) {
                $erros["itens.{$i}.valor"] = "{$produto->descricao}: o desconto tem que ser menor que 100%.";
            }
            $referencia = $tabela->precosSemPromocao($produto, $config)['dinheiro_pix'];
            if ($item['tipo'] === 'preco' && (float) $item['valor'] >= $referencia) {
                $erros["itens.{$i}.valor"] = "{$produto->descricao}: o preço da promoção (R$ "
                    . number_format((float) $item['valor'], 2, ',', '.') . ') não é menor que o preço à vista (R$ '
                    . number_format($referencia, 2, ',', '.') . ').';
            }
        }

        $inicio = Carbon::parse($dados['data_inicio']);
        $fim = Carbon::parse($dados['data_fim']);
        $conflitos = $promocoes->conflitos($empresaId, $produtos->keys()->all(), array_map('intval', $dados['unidades']), $inicio, $fim);
        if ($conflitos->isNotEmpty()) {
            $erros['conflitos'] = $conflitos->take(15)->map(fn ($c) => "{$c['produto']} já está na promoção \"{$c['promocao']}\" ({$c['periodo']}, {$c['lojas']})")
                ->push($conflitos->count() > 15 ? '… e mais ' . ($conflitos->count() - 15) . ' produto(s).' : null)
                ->filter()->all();
        }

        if ($erros) {
            throw ValidationException::withMessages($erros);
        }

        $promocao = DB::transaction(function () use ($dados, $empresaId, $produtos, $tabela, $config, $inicio, $fim) {
            $origem = ! empty($dados['origem_id'])
                ? Promocao::whereKey((int) $dados['origem_id'])->value('id') : null;

            $promocao = Promocao::create([
                'empresa_id' => $empresaId,
                'nome' => trim($dados['nome']),
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => $fim->toDateString(),
                'origem_id' => $origem,
                'criada_por' => auth()->id(),
            ]);
            $promocao->unidades()->sync(array_map('intval', $dados['unidades']));

            foreach ($dados['itens'] as $item) {
                $produto = $produtos->get((int) $item['produto_id']);
                $promocao->itens()->create([
                    'produto_id' => $produto->id,
                    'tipo' => $item['tipo'],
                    'valor' => round((float) $item['valor'], 2),
                    'preco_referencia' => $tabela->precosSemPromocao($produto, $config)['dinheiro_pix'],
                ]);
            }

            return $promocao;
        });

        return redirect()->route('app.promocoes.show', $promocao)
            ->with('success', "Promoção \"{$promocao->nome}\" criada com {$promocao->itens()->count()} produto(s).");
    }

    public function show(Promocao $promocao, TabelaPrecoService $tabela)
    {
        $promocao->load(['itens.produto', 'unidades' => fn ($q) => $q->withoutGlobalScopes(), 'origem', 'criador']);
        $config = ConfiguracaoLoja::daUnidade();

        $linhas = $promocao->itens->map(function ($item) use ($tabela, $config) {
            $atual = $item->produto ? $tabela->precosSemPromocao($item->produto, $config)['dinheiro_pix'] : null;

            return [
                'item' => $item,
                'preco_atual' => $atual,
                'preco_promocao' => $atual !== null ? $item->precoPromocional($atual) : null,
            ];
        });

        return view('app.promocoes.show', compact('promocao', 'linhas'));
    }

    /** Encerra agora: a promoção para de valer hoje mesmo (e a agendada nem começa). */
    public function encerrar(Promocao $promocao)
    {
        if (in_array($promocao->status(), ['encerrada', 'encerrada_antes'], true)) {
            return back()->with('error', 'Esta promoção já está encerrada.');
        }

        $promocao->update(['encerrada_em' => now(), 'encerrada_por' => auth()->id()]);

        return redirect()->route('app.promocoes.show', $promocao)
            ->with('success', 'Promoção encerrada: os produtos voltaram ao preço normal.');
    }

    /** Excluir só a que ainda não começou (a que já valeu fica no histórico). */
    public function destroy(Promocao $promocao)
    {
        if ($promocao->status() !== 'agendada') {
            return back()->with('error', 'Só dá para excluir promoção que ainda não começou. Use "Encerrar".');
        }

        $nome = $promocao->nome;
        $promocao->delete();

        return redirect()->route('app.promocoes.index')->with('success', "Promoção \"{$nome}\" excluída.");
    }

    /** Lojas em que o usuário pode criar promoção: dono/admin = todas; demais = as vinculadas. */
    private function lojasDoUsuario(): Collection
    {
        $user = auth()->user();
        $q = ($user->isDono() || $user->isAdmin() || $user->is_admin)
            ? Unidade::withoutGlobalScopes()->where('empresa_id', session('empresa_id'))
            : $user->unidades()->wherePivot('user_id', $user->id)->where('unidades.empresa_id', session('empresa_id'));

        return $q->where('unidades.status', 'ativa')->orderBy('nome')->get(['unidades.id', 'unidades.nome']);
    }

    private function itensParaTela(Promocao $promocao, TabelaPrecoService $tabela): array
    {
        $config = ConfiguracaoLoja::daUnidade();

        return $promocao->itens
            ->filter(fn ($i) => $i->produto && ($i->produto->status ?? 'ativo') === 'ativo')
            ->map(fn ($i) => [
                'produto_id' => $i->produto_id,
                'descricao' => $i->produto->descricao,
                'codigo' => $i->produto->codigo_barras ?: ($i->produto->codigo_interno ?: $i->produto->sku),
                'preco_referencia' => $tabela->precosSemPromocao($i->produto, $config)['dinheiro_pix'],
                'tipo' => $i->tipo,
                'valor' => (float) $i->valor,
            ])->values()->all();
    }
}
