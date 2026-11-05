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
            ->with(['unidades' => fn ($q) => $q->withoutGlobalScopes(), 'atualizador:id,name'])
            ->withCount('itens')
            ->orderByRaw("situacao = 'rascunho' desc")
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
        $ultima = $base ? null : Promocao::publicadas()->withCount('itens')->latest('id')->first();

        $inicial = $this->estadoVazio() + [
            'nome' => $base ? $base->nome : '',
            'data_inicio' => '',
            'data_fim' => '',
            'unidades' => $base ? $base->unidades->pluck('id')->intersect($lojas->pluck('id'))->values()->all() : [],
            'itens' => $base ? $this->itensParaTela($base, $tabela) : [],
            'origem_id' => $base?->id,
        ];

        $editando = null;

        return view('app.promocoes.create', compact('lojas', 'inicial', 'base', 'ultima', 'editando'));
    }

    /**
     * Continuar um rascunho ou mudar uma promoção que ainda não começou (05/11/2026). Qualquer
     * atendente com acesso a Promoções continua o rascunho de outro — o rodapé diz quem salvou por último.
     */
    public function editar(Promocao $promocao, TabelaPrecoService $tabela)
    {
        if (! $promocao->podeEditar()) {
            return redirect()->route('app.promocoes.show', $promocao)
                ->with('error', 'Só dá para mudar rascunho ou promoção que ainda não começou. Use "Duplicar".');
        }

        $promocao->load(['itens.produto', 'unidades' => fn ($q) => $q->withoutGlobalScopes(), 'atualizador:id,name', 'criador:id,name']);
        $lojas = $this->lojasDoUsuario();

        if ($promocao->eRascunho()) {
            $r = $promocao->rascunho ?? [];
            $inicial = [
                'nome' => (string) ($r['nome'] ?? ''),
                'data_inicio' => (string) ($r['data_inicio'] ?? ''),
                'data_fim' => (string) ($r['data_fim'] ?? ''),
                'unidades' => collect($r['unidades'] ?? [])->map(fn ($u) => (int) $u)->intersect($lojas->pluck('id'))->values()->all(),
                'itens' => $this->itensDoRascunho($r['itens'] ?? [], $tabela),
                'origem_id' => $promocao->origem_id,
            ];
        } else {
            $inicial = [
                'nome' => $promocao->nome,
                'data_inicio' => $promocao->data_inicio->toDateString(),
                'data_fim' => $promocao->data_fim->toDateString(),
                'unidades' => $promocao->unidades->pluck('id')->intersect($lojas->pluck('id'))->values()->all(),
                'itens' => $this->itensParaTela($promocao, $tabela),
                'origem_id' => $promocao->origem_id,
            ];
        }
        $inicial += $this->estadoVazio();
        $inicial['promocao_id'] = $promocao->id;
        $inicial['versao'] = $this->versao($promocao);

        $editando = [
            'rascunho' => $promocao->eRascunho(),
            'salvo_por' => $promocao->atualizador?->name ?? $promocao->criador?->name,
            'salvo_em' => $promocao->updated_at?->format('d/m/Y H:i'),
        ];
        $base = null;
        $ultima = $promocao->eRascunho() && empty($inicial['itens'])
            ? Promocao::publicadas()->withCount('itens')->latest('id')->first() : null;

        return view('app.promocoes.create', compact('lojas', 'inicial', 'base', 'ultima', 'editando'));
    }

    /**
     * "Salvar rascunho" (AJAX, 05/11/2026): guarda o assistente como está, mesmo incompleto. Não mexe
     * em preço. Se outra pessoa salvou depois que você abriu (versão diferente), não sobrescreve: 409.
     */
    public function salvarRascunho(Request $request, TabelaPrecoService $tabela): JsonResponse
    {
        $dados = $request->validate([
            'promocao_id' => ['nullable', 'integer'],
            'versao' => ['nullable', 'string', 'max:40'],
            'nome' => ['nullable', 'string', 'max:120'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'unidades' => ['nullable', 'array'],
            'unidades.*' => ['integer'],
            'itens' => ['nullable', 'array', 'max:5000'],
            'itens.*.produto_id' => ['required', 'integer'],
            'itens.*.tipo' => ['nullable', 'in:preco,percentual'],
            'itens.*.valor' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'itens.*.cartao_tipo' => ['nullable', 'in:preco,percentual'],
            'itens.*.cartao_valor' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'origem_id' => ['nullable', 'integer'],
        ]);

        $promocao = null;
        if (! empty($dados['promocao_id'])) {
            $promocao = Promocao::find((int) $dados['promocao_id']);
            if (! $promocao || ! $promocao->eRascunho()) {
                return response()->json(['message' => 'Este rascunho não existe mais (foi salvo como promoção ou excluído). Recarregue a lista.'], 409);
            }
            if (($dados['versao'] ?? '') !== $this->versao($promocao)) {
                return response()->json(['message' => $this->avisoVersao($promocao)], 409);
            }
        }

        $empresaId = (int) session('empresa_id');
        $lojas = $this->lojasDoUsuario()->pluck('id');
        $ids = collect($dados['itens'] ?? [])->pluck('produto_id')->map(fn ($i) => (int) $i)->unique();
        $daEmpresa = Produto::where('empresa_id', $empresaId)->whereIn('id', $ids)->pluck('id')->flip();

        $estado = [
            'nome' => trim((string) ($dados['nome'] ?? '')),
            'data_inicio' => $dados['data_inicio'] ?? null,
            'data_fim' => $dados['data_fim'] ?? null,
            'unidades' => collect($dados['unidades'] ?? [])->map(fn ($u) => (int) $u)->intersect($lojas)->values()->all(),
            'itens' => collect($dados['itens'] ?? [])
                ->filter(fn ($i) => $daEmpresa->has((int) $i['produto_id']))
                ->unique('produto_id')
                ->map(fn ($i) => [
                    'produto_id' => (int) $i['produto_id'],
                    'tipo' => $i['tipo'] ?? 'percentual',
                    'valor' => isset($i['valor']) && $i['valor'] !== '' ? (float) $i['valor'] : null,
                    'cartao_tipo' => $i['cartao_tipo'] ?? null,
                    'cartao_valor' => isset($i['cartao_valor']) && $i['cartao_valor'] !== '' ? (float) $i['cartao_valor'] : null,
                ])->values()->all(),
        ];

        $atributos = [
            'nome' => $estado['nome'] !== '' ? $estado['nome'] : 'Rascunho sem nome',
            'data_inicio' => $estado['data_inicio'],
            'data_fim' => $estado['data_fim'],
            'rascunho' => $estado,
            'atualizada_por' => auth()->id(),
        ];

        if ($promocao) {
            $promocao->update($atributos);
        } else {
            $origem = ! empty($dados['origem_id']) ? Promocao::whereKey((int) $dados['origem_id'])->value('id') : null;
            $promocao = Promocao::create($atributos + [
                'empresa_id' => $empresaId,
                'situacao' => Promocao::RASCUNHO,
                'origem_id' => $origem,
                'criada_por' => auth()->id(),
            ]);
        }
        $promocao->refresh();

        return response()->json([
            'id' => $promocao->id,
            'versao' => $this->versao($promocao),
            'salvo_em' => $promocao->updated_at->format('d/m/Y H:i'),
            'salvo_por' => auth()->user()->name,
            'produtos' => count($estado['itens']),
            'url' => route('app.promocoes.editar', $promocao),
        ]);
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

        return response()->json($produtos->map(fn ($p) => $this->linhaProduto($p, $tabela, $config))->values());
    }

    /**
     * "Adicionar todos" (05/11/2026, STILO): todos os produtos ativos da empresa, sem buscar.
     * Fica de fora produto sem preço (brinde a R$ 0,00 não tem como entrar em promoção).
     */
    public function todosProdutos(TabelaPrecoService $tabela): JsonResponse
    {
        $config = ConfiguracaoLoja::daUnidade();
        $produtos = Produto::where('empresa_id', session('empresa_id'))
            ->where('status', 'ativo')
            ->with('precos')
            ->orderBy('descricao')
            ->limit(5000)
            ->get();

        $linhas = $produtos->map(fn ($p) => $this->linhaProduto($p, $tabela, $config));
        $comPreco = $linhas->filter(fn ($l) => $l['preco_referencia'] > 0)->values();

        return response()->json([
            'itens' => $comPreco,
            'sem_preco' => $linhas->count() - $comPreco->count(),
        ]);
    }

    /** Linha de produto do assistente: preço à vista e do cartão (crédito) desta loja, sem promoção. */
    private function linhaProduto(Produto $p, TabelaPrecoService $tabela, ConfiguracaoLoja $config): array
    {
        $precos = $tabela->precosSemPromocao($p, $config);

        return [
            'produto_id' => $p->id,
            'descricao' => $p->descricao,
            'codigo' => $p->codigo_barras ?: ($p->codigo_interno ?: $p->sku),
            'preco_referencia' => $precos['dinheiro_pix'],
            'preco_cartao_referencia' => $precos['credito'],
        ];
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
            // Cartão (05/11/2026): vazio = regra da loja (acréscimo sobre o à vista)
            'itens.*.cartao_tipo' => ['nullable', 'in:preco,percentual'],
            'itens.*.cartao_valor' => ['nullable', 'required_with:itens.*.cartao_tipo', 'numeric', 'gt:0', 'max:9999999'],
            'origem_id' => ['nullable', 'integer'],
            // Continuando um rascunho ou mudando uma agendada (05/11/2026)
            'promocao_id' => ['nullable', 'integer'],
            'versao' => ['nullable', 'string', 'max:40'],
        ], [
            'data_inicio.after_or_equal' => 'A promoção não pode começar antes de hoje.',
            'data_fim.after_or_equal' => 'O fim tem que ser no mesmo dia do início ou depois.',
            'unidades.required' => 'Escolha pelo menos uma loja.',
            'unidades.*.in' => 'Loja fora das suas lojas.',
            'itens.required' => 'Adicione pelo menos um produto.',
            'itens.*.produto_id.distinct' => 'Um produto apareceu duas vezes na lista.',
            'itens.*.valor.gt' => 'Todo produto precisa de preço ou % maior que zero.',
            'itens.*.cartao_valor.required_with' => 'Informe o valor do cartão (ou deixe "Regra da loja").',
            'itens.*.cartao_valor.gt' => 'O valor do cartão tem que ser maior que zero.',
        ], [
            'data_inicio' => 'início', 'data_fim' => 'fim',
        ]);

        $editada = null;
        if (! empty($dados['promocao_id'])) {
            $editada = Promocao::find((int) $dados['promocao_id']);
            if (! $editada || ! $editada->podeEditar()) {
                throw ValidationException::withMessages(['promocao_id' => 'Esta promoção não pode mais ser mudada (já começou, foi encerrada ou excluída). Use "Duplicar".']);
            }
            if (($dados['versao'] ?? '') !== $this->versao($editada)) {
                throw ValidationException::withMessages(['versao' => $this->avisoVersao($editada)]);
            }
        }

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
            $normais = $tabela->precosSemPromocao($produto, $config);
            $referencia = $normais['dinheiro_pix'];
            if ($referencia <= 0) {
                $erros["itens.{$i}.produto_id"] = "{$produto->descricao}: está sem preço (R$ 0,00) — não dá para entrar em promoção.";
                continue;
            }
            $cartaoTipo = $item['cartao_tipo'] ?? null;
            if ($cartaoTipo === 'percentual' && (float) ($item['cartao_valor'] ?? 0) >= 100) {
                $erros["itens.{$i}.cartao_valor"] = "{$produto->descricao}: o desconto do cartão tem que ser menor que 100%.";
            }
            if ($cartaoTipo === 'preco' && (float) ($item['cartao_valor'] ?? 0) >= $normais['credito']) {
                $erros["itens.{$i}.cartao_valor"] = "{$produto->descricao}: o preço do cartão na promoção (R$ "
                    . number_format((float) $item['cartao_valor'], 2, ',', '.') . ') não é menor que o do cartão normal (R$ '
                    . number_format($normais['credito'], 2, ',', '.') . ').';
            }
            if ($item['tipo'] === 'preco' && (float) $item['valor'] >= $referencia) {
                $erros["itens.{$i}.valor"] = "{$produto->descricao}: o preço da promoção (R$ "
                    . number_format((float) $item['valor'], 2, ',', '.') . ') não é menor que o preço à vista (R$ '
                    . number_format($referencia, 2, ',', '.') . ').';
            }
        }

        $inicio = Carbon::parse($dados['data_inicio']);
        $fim = Carbon::parse($dados['data_fim']);
        $conflitos = $promocoes->conflitos($empresaId, $produtos->keys()->all(), array_map('intval', $dados['unidades']), $inicio, $fim, $editada?->id);
        if ($conflitos->isNotEmpty()) {
            $erros['conflitos'] = $conflitos->take(15)->map(fn ($c) => "{$c['produto']} já está na promoção \"{$c['promocao']}\" ({$c['periodo']}, {$c['lojas']})")
                ->push($conflitos->count() > 15 ? '… e mais ' . ($conflitos->count() - 15) . ' produto(s).' : null)
                ->filter()->all();
        }

        if ($erros) {
            throw ValidationException::withMessages($erros);
        }

        $promocao = DB::transaction(function () use ($dados, $empresaId, $produtos, $tabela, $config, $inicio, $fim, $editada) {
            $origem = ! empty($dados['origem_id'])
                ? Promocao::whereKey((int) $dados['origem_id'])->value('id') : null;

            $campos = [
                'nome' => trim($dados['nome']),
                'situacao' => Promocao::PUBLICADA,
                'rascunho' => null,
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => $fim->toDateString(),
                'atualizada_por' => auth()->id(),
            ];
            if ($editada) {
                $editada->update($campos + ['origem_id' => $editada->origem_id ?? $origem]);
                $editada->itens()->delete();
                $promocao = $editada;
            } else {
                $promocao = Promocao::create($campos + [
                    'empresa_id' => $empresaId,
                    'origem_id' => $origem,
                    'criada_por' => auth()->id(),
                ]);
            }
            $promocao->unidades()->sync(array_map('intval', $dados['unidades']));

            foreach ($dados['itens'] as $item) {
                $produto = $produtos->get((int) $item['produto_id']);
                $normais = $tabela->precosSemPromocao($produto, $config);
                $temCartao = ! empty($item['cartao_tipo']) && (float) ($item['cartao_valor'] ?? 0) > 0;
                $promocao->itens()->create([
                    'produto_id' => $produto->id,
                    'tipo' => $item['tipo'],
                    'valor' => round((float) $item['valor'], 2),
                    'preco_referencia' => $normais['dinheiro_pix'],
                    'cartao_tipo' => $temCartao ? $item['cartao_tipo'] : null,
                    'cartao_valor' => $temCartao ? round((float) $item['cartao_valor'], 2) : null,
                    'preco_cartao_referencia' => $normais['credito'],
                ]);
            }

            return $promocao;
        });

        return redirect()->route('app.promocoes.show', $promocao)
            ->with('success', "Promoção \"{$promocao->nome}\" " . ($editada ? 'salva' : 'criada') . " com {$promocao->itens()->count()} produto(s).");
    }

    public function show(Promocao $promocao, TabelaPrecoService $tabela)
    {
        if ($promocao->eRascunho()) {
            return redirect()->route('app.promocoes.editar', $promocao);
        }

        $promocao->load(['itens.produto', 'unidades' => fn ($q) => $q->withoutGlobalScopes(), 'origem', 'criador']);
        $config = ConfiguracaoLoja::daUnidade();

        $linhas = $promocao->itens->map(function ($item) use ($tabela, $config) {
            $normais = $item->produto ? $tabela->precosSemPromocao($item->produto, $config) : null;

            return [
                'item' => $item,
                'preco_atual' => $normais['dinheiro_pix'] ?? null,
                'preco_promocao' => $normais ? $item->precoPromocional($normais['dinheiro_pix']) : null,
                'cartao_atual' => $normais['credito'] ?? null,
                'cartao_promocao' => $normais ? $item->precoCartaoPromocional($normais['credito']) : null,
            ];
        });

        return view('app.promocoes.show', compact('promocao', 'linhas'));
    }

    /** Encerra agora: a promoção para de valer hoje mesmo (e a agendada nem começa). */
    public function encerrar(Promocao $promocao)
    {
        if (in_array($promocao->status(), ['encerrada', 'encerrada_antes', 'rascunho'], true)) {
            return back()->with('error', 'Esta promoção já está encerrada.');
        }

        $promocao->update(['encerrada_em' => now(), 'encerrada_por' => auth()->id()]);

        return redirect()->route('app.promocoes.show', $promocao)
            ->with('success', 'Promoção encerrada: os produtos voltaram ao preço normal.');
    }

    /** Excluir só a que ainda não começou (a que já valeu fica no histórico). */
    public function destroy(Promocao $promocao)
    {
        if (! in_array($promocao->status(), ['agendada', 'rascunho'], true)) {
            return back()->with('error', 'Só dá para excluir promoção que ainda não começou. Use "Encerrar".');
        }

        $nome = $promocao->nome;
        $promocao->delete();

        return redirect()->route('app.promocoes.index')->with('success', "Promoção \"{$nome}\" excluída.");
    }

    private function estadoVazio(): array
    {
        return ['promocao_id' => null, 'versao' => null];
    }

    /** "Versão" do registro para não sobrescrever o que outra pessoa salvou depois. */
    private function versao(Promocao $p): string
    {
        return (string) optional($p->updated_at)->format('Y-m-d H:i:s.u');
    }

    private function avisoVersao(Promocao $p): string
    {
        $p->loadMissing('atualizador:id,name');

        return ($p->atualizador?->name ?? 'Outra pessoa') . ' salvou esta promoção às ' . $p->updated_at->format('H:i')
            . ' de ' . $p->updated_at->format('d/m') . ', depois que você abriu. Para não apagar o que foi feito, abra de novo pela lista de Promoções.';
    }

    /** Itens do rascunho para a tela, com nome e preços normais de hoje. */
    private function itensDoRascunho(array $itens, TabelaPrecoService $tabela): array
    {
        $config = ConfiguracaoLoja::daUnidade();
        $produtos = Produto::where('empresa_id', session('empresa_id'))
            ->whereIn('id', collect($itens)->pluck('produto_id'))->with('precos')->get()->keyBy('id');

        return collect($itens)->filter(fn ($i) => $produtos->has($i['produto_id']))
            ->map(fn ($i) => $this->linhaProduto($produtos[$i['produto_id']], $tabela, $config) + [
                'tipo' => $i['tipo'] ?? 'percentual',
                'valor' => $i['valor'] ?? '',
                'cartao_tipo' => $i['cartao_tipo'] ?? '',
                'cartao_valor' => $i['cartao_valor'] ?? '',
            ])->values()->all();
    }

    /** Lojas em que o usuário pode criar promoção: dono/admin = todas; demais = as vinculadas. */
    private function lojasDoUsuario(): Collection
    {
        $user = auth()->user();
        $q = ($user->isDono() || $user->isAdmin() || $user->is_admin)
            ? Unidade::withoutGlobalScopes()->where('empresa_id', session('empresa_id'))
            : $user->unidades()->wherePivot('user_id', $user->id)->where('unidades.empresa_id', session('empresa_id'));

        $lojas = $q->where('unidades.status', 'ativa')->orderBy('nome')->get(['unidades.id', 'unidades.nome']);

        // Sem vínculo em unidade_user (comum no vendedor que ganhou a chave
        // `pode_editar_produtos`): oferece a loja da sessão, em vez de um
        // assistente sem loja nenhuma (05/11/2026).
        if ($lojas->isEmpty() && session('unidade_id')) {
            $lojas = Unidade::withoutGlobalScopes()
                ->where('empresa_id', session('empresa_id'))
                ->where('id', session('unidade_id'))
                ->where('status', 'ativa')
                ->get(['id', 'nome']);
        }

        return $lojas;
    }

    private function itensParaTela(Promocao $promocao, TabelaPrecoService $tabela): array
    {
        $config = ConfiguracaoLoja::daUnidade();

        return $promocao->itens
            ->filter(fn ($i) => $i->produto && ($i->produto->status ?? 'ativo') === 'ativo')
            ->map(fn ($i) => $this->linhaProduto($i->produto, $tabela, $config) + [
                'tipo' => $i->tipo,
                'valor' => (float) $i->valor,
                'cartao_tipo' => $i->temCartao() ? $i->cartao_tipo : '',
                'cartao_valor' => $i->temCartao() ? (float) $i->cartao_valor : '',
            ])->values()->all();
    }
}
