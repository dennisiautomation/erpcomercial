<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdemServicoController extends Controller
{
    public function index(Request $request)
    {
        $query = OrdemServico::where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->with(['cliente:id,nome_razao_social', 'vendedor:id,name', 'tecnico:id,name']);

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                  ->orWhere('equipamento', 'like', "%{$busca}%")
                  ->orWhereHas('cliente', fn ($c) => $c->where('nome_razao_social', 'like', "%{$busca}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $ordensServico = $query->latest()->paginate(20)->withQueryString();

        // Summary counts
        $empresaId = session('empresa_id');
        $unidadeId = session('unidade_id');
        $baseQuery = OrdemServico::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId);

        $abertas = (clone $baseQuery)->where('status', 'aberta')->count();
        $emAndamento = (clone $baseQuery)->where('status', 'em_andamento')->count();
        $aguardandoPeca = (clone $baseQuery)->where('status', 'aguardando_peca')->count();
        $concluidasMes = (clone $baseQuery)->where('status', 'concluida')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        return view('app.ordens-servico.index', compact(
            'ordensServico',
            'abertas',
            'emAndamento',
            'aguardandoPeca',
            'concluidasMes'
        ));
    }

    public function create()
    {
        $clientes = Cliente::where('empresa_id', session('empresa_id'))->orderBy('nome_razao_social')->get();
        $vendedores = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();
        $tecnicos = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();
        $produtos = Produto::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();
        $servicos = Servico::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();

        return view('app.ordens-servico.create', compact('clientes', 'vendedores', 'tecnicos', 'produtos', 'servicos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'equipamento' => 'required|string|max:255',
            'defeito_relatado' => 'required|string',
            'vendedor_id' => 'nullable|exists:users,id',
            'tecnico_id' => 'nullable|exists:users,id',
            'itens' => 'nullable|array',
            'itens.*.tipo' => 'required_with:itens|in:produto,servico',
            'itens.*.produto_id' => 'nullable|exists:produtos,id',
            'itens.*.servico_id' => 'nullable|exists:servicos,id',
            'itens.*.descricao' => 'required_with:itens|string',
            'itens.*.quantidade' => 'required_with:itens|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required_with:itens|numeric|min:0',
        ]);

        $os = DB::transaction(function () use ($request) {
            // Auto-generate numero
            $ultimoNumero = OrdemServico::where('empresa_id', session('empresa_id'))
                ->max('numero') ?? 0;

            $os = OrdemServico::create([
                'empresa_id' => session('empresa_id'),
                'unidade_id' => session('unidade_id'),
                'cliente_id' => $request->cliente_id,
                'vendedor_id' => $request->vendedor_id,
                'tecnico_id' => $request->tecnico_id,
                'numero' => $ultimoNumero + 1,
                'equipamento' => $request->equipamento,
                'defeito_relatado' => $request->defeito_relatado,
                'status' => 'aberta',
                'observacoes' => $request->observacoes,
                'valor_produtos' => 0,
                'valor_servicos' => 0,
                'desconto' => $request->desconto ?? 0,
                'total' => 0,
            ]);

            $valorProdutos = 0;
            $valorServicos = 0;

            if ($request->has('itens')) {
                foreach ($request->itens as $item) {
                    if (empty($item['descricao'])) continue;

                    $total = $item['quantidade'] * $item['preco_unitario'];

                    OrdemServicoItem::create([
                        'ordem_servico_id' => $os->id,
                        'tipo' => $item['tipo'],
                        'produto_id' => $item['tipo'] === 'produto' ? ($item['produto_id'] ?? null) : null,
                        'servico_id' => $item['tipo'] === 'servico' ? ($item['servico_id'] ?? null) : null,
                        'descricao' => $item['descricao'],
                        'quantidade' => $item['quantidade'],
                        'preco_unitario' => $item['preco_unitario'],
                        'total' => $total,
                    ]);

                    if ($item['tipo'] === 'produto') {
                        $valorProdutos += $total;
                    } else {
                        $valorServicos += $total;
                    }
                }
            }

            $desconto = $request->desconto ?? 0;
            $os->update([
                'valor_produtos' => $valorProdutos,
                'valor_servicos' => $valorServicos,
                'desconto' => $desconto,
                'total' => $valorProdutos + $valorServicos - $desconto,
            ]);

            return $os;
        });

        return redirect()->route('app.ordens-servico.show', $os)
            ->with('success', 'Ordem de Servico #' . $os->numero . ' criada com sucesso!');
    }

    public function show(Request $request, OrdemServico $ordemServico)
    {
        $ordemServico->load(['cliente', 'vendedor', 'tecnico', 'itens.produto', 'itens.servico', 'unidade']);

        if ($request->has('print')) {
            return view('app.ordens-servico.print', compact('ordemServico'));
        }

        return view('app.ordens-servico.show', compact('ordemServico'));
    }

    public function edit(OrdemServico $ordemServico)
    {
        $ordemServico->load(['itens']);

        $clientes = Cliente::where('empresa_id', session('empresa_id'))->orderBy('nome_razao_social')->get();
        $vendedores = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();
        $tecnicos = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();
        $produtos = Produto::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();
        $servicos = Servico::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();

        return view('app.ordens-servico.edit', compact('ordemServico', 'clientes', 'vendedores', 'tecnicos', 'produtos', 'servicos'));
    }

    public function update(Request $request, OrdemServico $ordemServico)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'equipamento' => 'required|string|max:255',
            'defeito_relatado' => 'required|string',
            'vendedor_id' => 'nullable|exists:users,id',
            'tecnico_id' => 'nullable|exists:users,id',
            'itens' => 'nullable|array',
            'itens.*.tipo' => 'required_with:itens|in:produto,servico',
            'itens.*.produto_id' => 'nullable|exists:produtos,id',
            'itens.*.servico_id' => 'nullable|exists:servicos,id',
            'itens.*.descricao' => 'required_with:itens|string',
            'itens.*.quantidade' => 'required_with:itens|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required_with:itens|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $ordemServico) {
            $ordemServico->update([
                'cliente_id' => $request->cliente_id,
                'vendedor_id' => $request->vendedor_id,
                'tecnico_id' => $request->tecnico_id,
                'equipamento' => $request->equipamento,
                'defeito_relatado' => $request->defeito_relatado,
                'observacoes' => $request->observacoes,
            ]);

            // Remove old items and recreate
            $ordemServico->itens()->delete();

            $valorProdutos = 0;
            $valorServicos = 0;

            if ($request->has('itens')) {
                foreach ($request->itens as $item) {
                    if (empty($item['descricao'])) continue;

                    $total = $item['quantidade'] * $item['preco_unitario'];

                    OrdemServicoItem::create([
                        'ordem_servico_id' => $ordemServico->id,
                        'tipo' => $item['tipo'],
                        'produto_id' => $item['tipo'] === 'produto' ? ($item['produto_id'] ?? null) : null,
                        'servico_id' => $item['tipo'] === 'servico' ? ($item['servico_id'] ?? null) : null,
                        'descricao' => $item['descricao'],
                        'quantidade' => $item['quantidade'],
                        'preco_unitario' => $item['preco_unitario'],
                        'total' => $total,
                    ]);

                    if ($item['tipo'] === 'produto') {
                        $valorProdutos += $total;
                    } else {
                        $valorServicos += $total;
                    }
                }
            }

            $desconto = $request->desconto ?? 0;
            $ordemServico->update([
                'valor_produtos' => $valorProdutos,
                'valor_servicos' => $valorServicos,
                'desconto' => $desconto,
                'total' => $valorProdutos + $valorServicos - $desconto,
            ]);
        });

        return redirect()->route('app.ordens-servico.show', $ordemServico)
            ->with('success', 'Ordem de Servico atualizada com sucesso!');
    }

    public function updateStatus(Request $request, OrdemServico $ordemServico)
    {
        $request->validate([
            'status' => 'required|in:aberta,em_andamento,aguardando_peca,concluida,entregue,cancelada',
            'laudo_tecnico' => 'nullable|string',
        ]);

        $statusPermitidos = [
            'aberta' => ['em_andamento', 'cancelada'],
            'em_andamento' => ['aguardando_peca', 'concluida', 'cancelada'],
            'aguardando_peca' => ['em_andamento', 'concluida', 'cancelada'],
            'concluida' => ['entregue', 'cancelada'],
            'entregue' => [],
            'cancelada' => [],
        ];

        $novoStatus = $request->status;

        if ($novoStatus !== 'cancelada' && !in_array($novoStatus, $statusPermitidos[$ordemServico->status] ?? [])) {
            return back()->with('error', 'Transicao de status invalida.');
        }

        $dados = ['status' => $novoStatus];

        if ($novoStatus === 'concluida' && $request->filled('laudo_tecnico')) {
            $dados['laudo_tecnico'] = $request->laudo_tecnico;
        }

        $ordemServico->update($dados);

        return back()->with('success', 'Status atualizado para ' . str_replace('_', ' ', $novoStatus) . '.');
    }

    public function converterEmVenda(OrdemServico $ordemServico)
    {
        if (!in_array($ordemServico->status, ['concluida', 'entregue'])) {
            return back()->with('error', 'Apenas OS concluidas ou entregues podem ser convertidas em venda.');
        }

        $venda = DB::transaction(function () use ($ordemServico) {
            $ultimoNumero = Venda::where('empresa_id', session('empresa_id'))
                ->max('numero') ?? 0;

            $venda = Venda::create([
                'empresa_id' => $ordemServico->empresa_id,
                'unidade_id' => $ordemServico->unidade_id,
                'cliente_id' => $ordemServico->cliente_id,
                'vendedor_id' => $ordemServico->vendedor_id,
                'numero' => $ultimoNumero + 1,
                'tipo' => 'venda',
                'status' => 'finalizada',
                'subtotal' => $ordemServico->valor_produtos + $ordemServico->valor_servicos,
                'desconto' => $ordemServico->desconto,
                'total' => $ordemServico->total,
                'forma_pagamento' => 'dinheiro',
                'observacoes' => 'Gerada a partir da OS #' . $ordemServico->numero,
            ]);

            foreach ($ordemServico->itens as $item) {
                VendaItem::create([
                    'venda_id' => $venda->id,
                    'produto_id' => $item->produto_id,
                    'descricao' => $item->descricao,
                    'quantidade' => $item->quantidade,
                    'preco_unitario' => $item->preco_unitario,
                    'desconto' => 0,
                    'total' => $item->total,
                ]);
            }

            $ordemServico->update(['status' => 'entregue']);

            return $venda;
        });

        return redirect()->route('app.vendas.show', $venda)
            ->with('success', 'Venda #' . $venda->numero . ' gerada a partir da OS #' . $ordemServico->numero . '.');
    }

    public function destroy(OrdemServico $ordemServico)
    {
        if (in_array($ordemServico->status, ['entregue', 'cancelada'])) {
            return back()->with('error', 'Nao e possivel excluir esta OS.');
        }

        $ordemServico->itens()->delete();
        $ordemServico->delete();

        return redirect()->route('app.ordens-servico.index')
            ->with('success', 'Ordem de Servico excluida com sucesso.');
    }
}
