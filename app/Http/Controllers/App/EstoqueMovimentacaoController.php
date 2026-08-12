<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusComodato;
use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\Estoque;
use App\Models\EstoqueComodato;
use App\Models\EstoqueMovimentacao;
use App\Models\Produto;
use App\Services\SaldoEstoque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstoqueMovimentacaoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $query = EstoqueMovimentacao::with(['produto', 'user', 'unidade'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('produto_id')) {
            $query->where('produto_id', $request->produto_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        $movimentacoes = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $produtos = Produto::where('empresa_id', $empresaId)
            ->orderBy('descricao')
            ->get(['id', 'descricao']);

        // Summary cards
        $totalEntradas = EstoqueMovimentacao::where('empresa_id', $empresaId)
            ->whereIn('tipo', ['entrada', 'devolucao'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalSaidas = EstoqueMovimentacao::where('empresa_id', $empresaId)
            ->whereIn('tipo', ['saida', 'perda'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalAjustes = EstoqueMovimentacao::where('empresa_id', $empresaId)
            ->whereIn('tipo', ['ajuste', 'bonificacao'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalTransferencias = EstoqueMovimentacao::where('empresa_id', $empresaId)
            ->where('tipo', 'transferencia')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('app.estoque.movimentacoes.index', compact(
            'movimentacoes', 'produtos', 'totalEntradas', 'totalSaidas', 'totalAjustes', 'totalTransferencias'
        ));
    }

    public function create()
    {
        $produtos = Produto::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativo')
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'estoque_minimo']);

        // A view só mostra o seletor se vier mais de um — loja com estoque
        // único continua com a tela idêntica à de antes.
        $estoques = Estoque::withoutGlobalScopes()
            ->where('unidade_id', session('unidade_id'))
            ->where('status', 'ativo')
            ->orderByDesc('is_padrao')
            ->orderBy('nome')
            ->get(['id', 'nome', 'is_padrao']);

        return view('app.estoque.movimentacoes.create', compact('produtos', 'estoques'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo'                    => 'required|in:ajuste,perda,bonificacao,entrada',
            'itens'                   => 'required|array|min:1',
            'itens.*.produto_id'      => 'required|exists:produtos,id',
            'itens.*.quantidade'      => 'required|numeric|min:0.001',
            'itens.*.custo_unitario'  => 'nullable|numeric|min:0',
            'estoque_id'              => 'nullable|exists:estoques,id',
            'observacoes'             => 'nullable|string|max:500',
            // Bonificação que deve voltar (influencer, showroom, editorial)
            'retorno_previsto'        => 'nullable|boolean',
            'responsavel'             => 'nullable|string|max:120|required_if:retorno_previsto,1',
            'contato'                 => 'nullable|string|max:120',
            'data_prevista_retorno'   => 'nullable|date|after_or_equal:today|required_if:retorno_previsto,1',
        ], [
            'responsavel.required_if' => 'Diga com quem a peça vai ficar.',
            'data_prevista_retorno.required_if' => 'Informe a data prevista de retorno.',
            'data_prevista_retorno.after_or_equal' => 'A data prevista de retorno não pode ser no passado.',
        ]);

        // Só bonificação gera controle de retorno — nos outros tipos o bloco
        // nem aparece na tela, mas a guarda evita comodato órfão via POST torto.
        $comComodato = $validated['tipo'] === 'bonificacao'
            && $request->boolean('retorno_previsto');

        $unidadeId = (int) session('unidade_id');

        // Estoque escolhido precisa ser DESTA loja — senão a movimentação
        // cairia no depósito de outra unidade.
        $estoqueId = null;
        if (! empty($validated['estoque_id'])) {
            $estoqueId = Estoque::withoutGlobalScopes()
                ->where('id', $validated['estoque_id'])
                ->where('unidade_id', $unidadeId)
                ->value('id');
        }
        // Loja com um estoque só nem manda o campo: cai no de venda.
        $estoqueId ??= SaldoEstoque::estoqueDeVendaId($unidadeId);

        if (! $estoqueId) {
            return back()->withInput()
                ->with('error', 'Esta loja não tem estoque cadastrado. Crie um em Configurações da Loja.');
        }

        DB::transaction(function () use ($validated, $comComodato, $estoqueId, $unidadeId) {
            $tipo = TipoMovimentacaoEstoque::from($validated['tipo']);

            foreach ($validated['itens'] as $item) {
                $produto = Produto::lockForUpdate()->findOrFail($item['produto_id']);

                // A cadeia anterior→posterior é por ESTOQUE (antes era por
                // unidade); misturar estoques corromperia o histórico.
                $estoqueAnterior = SaldoEstoque::noEstoque($estoqueId, $produto->id);

                $quantidade = (float) $item['quantidade'];

                // Determine stock change based on type
                $delta = match ($tipo) {
                    TipoMovimentacaoEstoque::Entrada, TipoMovimentacaoEstoque::Ajuste => $quantidade,
                    TipoMovimentacaoEstoque::Perda, TipoMovimentacaoEstoque::Bonificacao => -$quantidade,
                    default => $quantidade,
                };

                $movimentacao = EstoqueMovimentacao::create([
                    'empresa_id'          => auth()->user()->empresa_id,
                    'unidade_id'          => $unidadeId,
                    'estoque_id'          => $estoqueId,
                    'produto_id'          => $produto->id,
                    'tipo'                => $validated['tipo'],
                    'quantidade'          => $quantidade,
                    'quantidade_anterior' => $estoqueAnterior,
                    'quantidade_posterior' => $estoqueAnterior + $delta,
                    'custo_unitario'      => $item['custo_unitario'] ?? 0,
                    'user_id'             => auth()->id(),
                    'observacoes'         => $validated['observacoes'] ?? null,
                ]);

                // A peça saiu, mas é emprestada: registra com quem ficou e até quando.
                if ($comComodato) {
                    EstoqueComodato::create([
                        'empresa_id'              => auth()->user()->empresa_id,
                        'unidade_id'              => $unidadeId,
                        'estoque_movimentacao_id' => $movimentacao->id,
                        'produto_id'              => $produto->id,
                        'quantidade'              => $quantidade,
                        'quantidade_devolvida'    => 0,
                        'responsavel'             => $validated['responsavel'],
                        'contato'                 => $validated['contato'] ?? null,
                        'data_saida'              => now()->toDateString(),
                        'data_prevista_retorno'   => $validated['data_prevista_retorno'],
                        'status'                  => StatusComodato::Pendente,
                        'observacoes'             => $validated['observacoes'] ?? null,
                        'user_id'                 => auth()->id(),
                    ]);
                }
            }
        });

        $qtd = count($validated['itens']);

        return redirect()->route('app.movimentacoes.index')
            ->with('success', $qtd > 1
                ? "{$qtd} movimentações registradas com sucesso!"
                : 'Movimentacao registrada com sucesso!');
    }

    public function show(EstoqueMovimentacao $movimentacao)
    {
        $movimentacao->load(['produto', 'user', 'unidade']);

        return view('app.estoque.movimentacoes.show', compact('movimentacao'));
    }
}
