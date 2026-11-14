<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusCaixa;
use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoCaixa;
use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Models\Comissao;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Devolucao;
use App\Models\MovimentacaoCaixa;
use App\Models\Produto;
use App\Scopes\UnidadeScope;
use App\Services\LancamentoCaixaService;
use App\Services\SaldoEstoque;
use App\Models\User;
use App\Models\Vale;
use App\Models\ValeUso;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendaController extends Controller
{
    public function index(Request $request)
    {
        // Filtro por loja: só para admin/dono (o UnidadeScope já trava os demais
        // perfis na loja da sessão). Padrão continua sendo a loja atual.
        $lojas = $this->lojasParaFiltro($request->user());
        $lojaFiltro = (int) session('unidade_id');
        if ($lojas->count() > 1 && $request->filled('loja')) {
            if ($request->loja === 'todas') {
                $lojaFiltro = null;
            } elseif ($lojas->contains('id', (int) $request->loja)) {
                $lojaFiltro = (int) $request->loja;
            }
        }

        // Origem (06/11/2026): um filtro só no lugar de Tipo + Canal (eram quase
        // redundantes — ver Venda::ORIGENS). O tipo segue visível na lista.
        $tipos = \App\Models\Venda::TIPOS_LABELS;
        $origens = \App\Models\Venda::ORIGENS;
        $origemFiltro = array_key_exists((string) $request->origem, $origens) ? (string) $request->origem : null;

        $base = fn () => Venda::where('empresa_id', session('empresa_id'))
            ->when($lojaFiltro, fn ($q) => $q->where('unidade_id', $lojaFiltro))
            ->when(! $lojaFiltro, fn ($q) => $q->whereIn('unidade_id', $lojas->pluck('id')));

        // Mesmos filtros na lista E nos cartões do topo (antes os cartões só
        // olhavam loja e datas — filtrar tipo/status/busca não mudava nada neles).
        $filtros = function ($q, bool $comDatas = true) use ($request, $origemFiltro) {
            if ($request->filled('busca')) {
                $busca = $request->busca;
                $q->where(function ($q) use ($busca) {
                    $q->where('numero', 'like', "%{$busca}%")
                      ->orWhereHas('cliente', fn ($c) => $c->where('nome_razao_social', 'like', "%{$busca}%"));
                });
            }
            if ($request->filled('status')) {
                $q->where('status', $request->status);
            }
            if ($origemFiltro) {
                $q->daOrigem($origemFiltro);
            }
            // Forma de pagamento (14/11/2026): linha de forma da empresa — venda com
            // algum pagamento nela, split incluso (antes só casava venda de forma única)
            if ($request->filled('forma_pagamento')) {
                $q->comForma((string) $request->forma_pagamento);
            }
            if ($comDatas && $request->filled('data_inicio')) {
                $q->whereDate('created_at', '>=', $request->data_inicio);
            }
            if ($comDatas && $request->filled('data_fim')) {
                $q->whereDate('created_at', '<=', $request->data_fim);
            }

            return $q;
        };

        $vendas = $filtros($base()->with(['cliente:id,nome_razao_social,cpf_cnpj', 'vendedor:id,name', 'unidade:id,nome', 'caixa:id,numero_caixa']))
            ->latest()->paginate(20)->withQueryString();

        $statsQuery = $filtros($base());
        $stats = [
            'total_concluidas' => (clone $statsQuery)->where('status', StatusVenda::Concluida)->sum('total'),
            'count_concluidas' => (clone $statsQuery)->where('status', StatusVenda::Concluida)->count(),
            'count_canceladas' => (clone $statsQuery)->where('status', StatusVenda::Cancelada)->count(),
            'total_hoje' => $filtros($base(), false)->where('status', StatusVenda::Concluida)->whereDate('created_at', today())->sum('total'),
        ];

        $formasFiltro = app(\App\Services\FormaPagamentoService::class)
            ->opcoesFiltro((int) (session('empresa_id') ?? auth()->user()->empresa_id));

        return view('app.vendas.index', compact('vendas', 'stats', 'lojas', 'lojaFiltro', 'tipos', 'origens', 'origemFiltro', 'formasFiltro'));
    }

    /**
     * Lojas que o usuário pode usar no filtro da listagem: admin da plataforma
     * e admin/dono da empresa veem todas as unidades ativas da empresa da
     * sessão; demais perfis não filtram (UnidadeScope já os trava).
     */
    private function lojasParaFiltro($user): \Illuminate\Support\Collection
    {
        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;

        if (! $user->is_admin && ! in_array($perfil, ['admin', 'dono'])) {
            return collect();
        }

        return \App\Models\Unidade::withoutGlobalScopes()
            ->where('empresa_id', session('empresa_id'))
            ->where('status', 'ativa')
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    public function create()
    {
        $vendedores = User::where('empresa_id', auth()->user()->empresa_id)
            ->whereIn('perfil', ['vendedor', 'gerente', 'dono'])
            ->where('status', 'ativo')
            ->orderBy('name')
            ->get();

        // Formas da empresa (Configurações → Formas de pagamento, 14/11/2026)
        $formasPagamento = app(\App\Services\FormaPagamentoService::class)->ativas((int) (session('empresa_id') ?? auth()->user()->empresa_id));

        return view('app.vendas.create', compact('vendedores', 'formasPagamento'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'itens'                  => 'required|array|min:1',
            'itens.*.produto_id'     => 'required|exists:produtos,id',
            'itens.*.quantidade'     => 'required|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.desconto_valor' => 'nullable|numeric|min:0',
            'cliente_id'             => 'nullable|exists:clientes,id',
            'vendedor_id'            => 'nullable|exists:users,id',
            'forma_pagamento'        => 'required|string',
            'desconto_valor'         => 'nullable|numeric|min:0',
            'observacoes'            => 'nullable|string|max:1000',
        ]);

        // O select manda a chave do botão configurado (14/11/2026); daqui para baixo
        // forma_pagamento volta a ser o código de sempre e o nome vai para a venda.
        $fpBalcao = app(\App\Services\FormaPagamentoService::class)
            ->resolver((int) (session('empresa_id') ?? auth()->user()->empresa_id), (string) $request->forma_pagamento);
        if (! $fpBalcao) {
            return back()->withInput()->withErrors(['forma_pagamento' => 'Esta forma de pagamento está desligada. Escolha outra.']);
        }
        $request->merge(['forma_pagamento' => $fpBalcao->codigoGravado()]);

        // Caixa (28/10/2026): a venda de balcão também entra no caixa aberto de
        // quem vende, como o PDV — antes nascia sem caixa e sumia do fechamento.
        $caixaSvc = app(LancamentoCaixaService::class);
        $caixa = $caixaSvc->caixaAbertoDoUsuario((int) session('unidade_id'), (int) auth()->id());
        if (! $caixa) {
            return back()->withInput()->with('error', 'Abra o caixa antes de registrar a venda: ela entra no caixa, como no PDV.');
        }

        try {
            $venda = DB::transaction(function () use ($fpBalcao, $request, $caixa, $caixaSvc) {
                $empresaId = session('empresa_id');
                $unidadeId = session('unidade_id');

                $ultimoNumero = Venda::withoutGlobalScopes()
                    ->where('empresa_id', $empresaId)
                    ->max('numero');
                $numero = $ultimoNumero ? $ultimoNumero + 1 : 1;

                $subtotal = 0;
                $itensData = [];
                $brutoVenda = 0.0;
                $descontoItens = 0.0;

                foreach ($request->itens as $item) {
                    $produto = Produto::find($item['produto_id']);
                    if (!$produto) continue;

                    $precoUnit = $item['preco_unitario'];
                    $qtd = $item['quantidade'];
                    $descontoValor = $item['desconto_valor'] ?? 0;
                    $totalItem = round(($precoUnit * $qtd) - $descontoValor, 2);
                    $brutoVenda += $precoUnit * $qtd;
                    $descontoItens += (float) $descontoValor;

                    $itensData[] = [
                        'produto_id'          => $item['produto_id'],
                        'descricao'           => $produto->descricao,
                        'quantidade'          => $qtd,
                        'preco_unitario'      => $precoUnit,
                        'desconto_valor'      => $descontoValor,
                        'desconto_percentual' => 0,
                        'total'               => $totalItem,
                    ];

                    $subtotal += $totalItem;
                }

                if (empty($itensData)) {
                    throw new \Exception('Nenhum item valido na venda.');
                }

                $descontoGeral = $request->desconto_valor ?? 0;

                // Desconto máximo da loja (06/11/2026): itens + geral
                \App\Models\ConfiguracaoLoja::daUnidade($empresaId, $unidadeId)
                    ->validarDesconto($brutoVenda, $descontoItens + (float) $descontoGeral);

                $total = round($subtotal - $descontoGeral, 2);
                if ($total < 0) $total = 0;

                $venda = Venda::create([
                    'empresa_id'          => $empresaId,
                    'unidade_id'          => $unidadeId,
                    'cliente_id'          => $request->cliente_id,
                    'vendedor_id'         => $request->vendedor_id ?? auth()->id(),
                    'caixa_id'            => $caixa->id,
                    'numero'              => $numero,
                    'subtotal'            => $subtotal,
                    'desconto_percentual' => 0,
                    'desconto_valor'      => $descontoGeral,
                    'total'               => $total,
                    'forma_pagamento'     => $request->forma_pagamento,
                    'pagamento_detalhes'  => [['forma' => $request->forma_pagamento, 'valor' => $total, 'forma_chave' => $fpBalcao->chave, 'forma_nome' => $fpBalcao->nome]],
                    'troco'               => 0,
                    'status'              => StatusVenda::Concluida,
                    'tipo'                => 'balcao',
                    'canal'               => \App\Enums\CanalVenda::Presencial->value,
                    'observacoes'         => $request->observacoes,
                ]);

                // Create VendaItens
                foreach ($itensData as $itemData) {
                    $venda->itens()->create($itemData);
                }

                // Deduct estoque
                foreach ($request->itens as $item) {
                    if (!empty($item['produto_id'])) {
                        $produto = Produto::find($item['produto_id']);
                        if (!$produto) continue;

                        SaldoEstoque::registrar(
                            $empresaId,
                            $unidadeId,
                            SaldoEstoque::estoqueDeVendaId($unidadeId),
                            (int) $item['produto_id'],
                            TipoMovimentacaoEstoque::Saida->value,
                            -(float) $item['quantidade'],
                            [
                                'custo_unitario' => $item['preco_unitario'],
                                'origem_tipo'    => Venda::class,
                                'origem_id'      => $venda->id,
                                'observacoes'    => "Venda Balcao #{$venda->numero}",
                            ]
                        );
                    }
                }

                // Entrada no caixa, uma por forma (aqui é uma só), com venda_id
                $caixaSvc->lancarVenda($venda, $caixa, $venda->pagamento_detalhes, (int) auth()->id());

                // Create ContaReceber
                ContaReceber::create([
                    'empresa_id'      => $empresaId,
                    'unidade_id'      => $unidadeId,
                    'cliente_id'      => $request->cliente_id,
                    'venda_id'        => $venda->id,
                    'descricao'       => "Venda Balcao #{$venda->numero} - " . ucfirst(str_replace('_', ' ', $request->forma_pagamento)),
                    'valor'           => $total,
                    'valor_pago'      => $total,
                    'vencimento'      => now(),
                    'pago_em'         => now(),
                    'forma_pagamento' => $request->forma_pagamento,
                    'forma_chave'     => $fpBalcao->chave, // botão configurado (14/11/2026)
                    'forma_nome'      => $fpBalcao->nome,
                    'parcela'         => 1,
                    'total_parcelas'  => 1,
                    'status'          => 'paga',
                ]);

                // Calculate and create Comissao for vendedor
                $vendedorId = $request->vendedor_id ?? auth()->id();
                if ($vendedorId) {
                    // % do cadastro; vazio = sem comissão (antes vazio virava 5% — 11/11/2026)
                    $percentualComissao = Comissao::percentualDoVendedor((int) $vendedorId);
                    $valorComissao = round($total * ($percentualComissao / 100), 2);

                    if ($valorComissao > 0) {
                        Comissao::create([
                            'empresa_id'     => $empresaId,
                            'unidade_id'     => $unidadeId,
                            'user_id'        => $vendedorId,
                            'venda_id'       => $venda->id,
                            'valor_venda'    => $total,
                            'percentual'     => $percentualComissao,
                            'valor_comissao' => $valorComissao,
                            'status'         => 'pendente',
                        ]);
                    }
                }

                return $venda;
            });

            return redirect()->route('app.vendas.show', $venda)
                ->with('success', "Venda #{$venda->numero} registrada com sucesso!");

        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Erro ao registrar venda: ' . $e->getMessage());
        }
    }

    public function show(Venda $venda)
    {
        $venda->load(['cliente', 'vendedor', 'itens.produto', 'itens.servico', 'caixa', 'pedido', 'notasFiscais', 'contasReceber',
            'devolucoes.itens.produto', 'devolucoes.itens.vendaItem', 'devolucoes.vale', 'devolucoes.vendaNova', 'devolucoes.user']);

        // Trocar vendedor (03/11/2026): mesma lista do F3 do PDV, pela loja da venda
        $vendedoresTroca = collect();
        if (\App\Http\Middleware\CheckPermission::canUser(auth()->user(), 'vendas', 'trocar_vendedor')) {
            $vendedoresTroca = PdvController::filtrarOperadoresPelaLoja(
                $this->vendedoresDaEmpresa($venda->empresa_id),
                $venda->empresa,
                (int) $venda->unidade_id
            );
        }
        $comissaoPaga = $venda->comissoes()->where('status', 'paga')->exists();

        return view('app.vendas.show', compact('venda', 'vendedoresTroca', 'comissaoPaga'));
    }

    /**
     * Corrige o vendedor de uma venda finalizada (03/11/2026) — o caixa esqueceu o
     * F3 e a venda ficou no nome de quem estava logado. Muda SÓ o vendedor e a
     * comissão dele (recalculada com o % do cadastro do vendedor novo — 11/11/2026);
     * itens, valores, caixa e nota não mudam. Comissão já paga trava a troca.
     */
    public function trocarVendedor(Request $request, Venda $venda)
    {
        $validos = PdvController::filtrarOperadoresPelaLoja(
            $this->vendedoresDaEmpresa($venda->empresa_id),
            $venda->empresa,
            (int) $venda->unidade_id
        );
        $dados = $request->validate([
            'vendedor_id' => ['required', 'integer', function ($attr, $valor, $falha) use ($validos) {
                if (! $validos->contains('id', (int) $valor)) {
                    $falha('Vendedor inválido para a loja desta venda.');
                }
            }],
        ]);

        $status = $venda->status instanceof StatusVenda ? $venda->status->value : $venda->status;
        if ($status === 'cancelada') {
            return back()->with('error', 'Venda cancelada não troca de vendedor.');
        }
        if ($venda->comissoes()->where('status', 'paga')->exists()) {
            return back()->with('error', 'A comissão desta venda já foi paga — não dá para trocar o vendedor.');
        }

        $antigo = $venda->vendedor;
        $novo = $validos->firstWhere('id', (int) $dados['vendedor_id']);
        if ((int) $venda->vendedor_id === (int) $novo->id) {
            return back()->with('success', 'A venda já está com este vendedor.');
        }

        DB::transaction(function () use ($venda, $novo, $antigo) {
            $venda->update(['vendedor_id' => $novo->id]);
            // A comissão pendente passa para o vendedor novo com o % do CADASTRO
            // dele (11/11/2026 — antes ia com o % do antigo); 0% ou vazio = sem comissão.
            $pctNovo = Comissao::percentualDoVendedor((int) $novo->id);
            foreach ($venda->comissoes()->where('status', 'pendente')->get() as $comissao) {
                $valor = round((float) $comissao->valor_venda * $pctNovo / 100, 2);
                if ($valor > 0) {
                    $comissao->update(['user_id' => $novo->id, 'percentual' => $pctNovo, 'valor_comissao' => $valor]);
                } else {
                    $comissao->delete();
                }
            }

            activity('Venda')
                ->performedOn($venda)
                ->causedBy(auth()->user())
                ->event('vendedor_trocado')
                ->withProperties(['de' => ['id' => $antigo?->id, 'nome' => $antigo?->name], 'para' => ['id' => $novo->id, 'nome' => $novo->name]])
                ->log('Vendedor trocado de ' . ($antigo?->name ?? '—') . ' para ' . $novo->name);
        });

        return back()->with('success', 'Vendedor da venda #' . ($venda->numero ?? $venda->id) . ' trocado para ' . $novo->name . '.');
    }

    /** Quem pode ser vendedor de uma venda — os mesmos perfis do F3 do PDV. */
    private function vendedoresDaEmpresa(int $empresaId)
    {
        return User::where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->whereIn('perfil', ['caixa', 'vendedor', 'gerente', 'dono'])
            ->select('id', 'name', 'perfil')
            ->orderBy('name')
            ->get();
    }

    /** Recibo/cupom imprimível da venda (com dados fiscais quando houver NFC-e). */
    public function recibo(Venda $venda)
    {
        $venda->load(['itens.produto', 'cliente', 'vendedor', 'empresa', 'unidade']);

        $notaFiscal = $venda->notasFiscais()
            ->where('tipo', 'nfce')
            ->whereNotIn('status', ['cancelada', 'rejeitada'])
            ->latest()
            ->first();

        return view('app.pdv.cupom-nao-fiscal', compact('venda', 'notaFiscal'));
    }

    public function destroy(Request $request, Venda $venda)
    {
        $ignorarNota = $request->boolean('ignorar_nota_vencida');
        $notaVencida = $ignorarNota ? \App\Services\VendaCancelamentoService::notaForaDoPrazo($venda) : null;

        try {
            app(\App\Services\VendaCancelamentoService::class)->cancelar($venda, (int) auth()->id(), false, $ignorarNota);
        } catch (\App\Services\VendaNaoCancelavelException $e) {
            // Documento fiscal vivo: leva direto para a nota (cancelar por lá primeiro)
            if ($e->nota) {
                return redirect()->route('app.notas-fiscais.show', $e->nota)->with('error', $e->getMessage());
            }

            return back()->with('error', $e->getMessage());
        }

        if ($notaVencida) {
            activity('Venda')
                ->performedOn($venda)
                ->causedBy(auth()->user())
                ->event('cancelada_nota_vencida')
                ->withProperties(['nota_fiscal_id' => $notaVencida->id, 'numero_nota' => $notaVencida->numero])
                ->log('Venda cancelada com NFC-e nº ' . $notaVencida->numero . ' fora do prazo de cancelamento (a nota segue autorizada na SEFAZ)');

            return back()->with('success', 'Venda cancelada: estoque e caixa estornados. Atenção: a NFC-e nº ' . $notaVencida->numero . ' passou do prazo e NÃO foi cancelada na SEFAZ — avise o contador.');
        }

        return back()->with('success', 'Venda cancelada com sucesso!');
    }
}
