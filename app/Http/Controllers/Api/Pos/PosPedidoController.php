<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\CanalVenda;
use App\Enums\StatusPedido;
use App\Http\Controllers\App\PedidoController;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ConfiguracaoFiscal;
use App\Models\PosEvento;
use App\Models\PosOperacao;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Venda;
use App\Scopes\UnidadeScope;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFCeService;
use App\Services\Pos\PosCupomService;
use App\Services\Pos\PosPermissoes;
use App\Services\Pos\PosStone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Emissão de pedido no POS (vendedor tira o pedido no balcão ou na rua) e
 * recebimento do pedido no caixa com cobrança na Stone.
 *
 * Criar segue o PedidoController::store (numeração, itens, desconto); o preço
 * é o do cadastro — o POS não digita preço. Confirmar e faturar passam pelo
 * PedidoController (contas a receber, baixa de estoque, entrada no caixa).
 */
class PosPedidoController extends Controller
{
    public function criar(Request $request): JsonResponse
    {
        $request->validate([
            'chave' => ['required', 'string', 'min:8', 'max:64'],
            'cliente_id' => ['required', 'integer'],
            'itens' => ['required', 'array', 'min:1', 'max:200'],
            'itens.*.produto_id' => ['required', 'integer'],
            'itens.*.quantidade' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'itens.*.desconto_percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'desconto_valor' => ['nullable', 'numeric', 'min:0'],
            'condicao_pagamento' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'confirmar' => ['nullable', 'boolean'],
            'colunas' => ['nullable', 'integer', 'min:24', 'max:48'],
        ]);
        $user = $request->user();
        if (! PosPermissoes::pode($user, 'pedidos', 'criar')) {
            return response()->json(['erro' => 'Seu perfil não emite pedidos.'], 403);
        }

        $empresaId = (int) session('empresa_id');
        $cliente = Cliente::withoutGlobalScopes()->where('empresa_id', $empresaId)->find($request->cliente_id);
        if (! $cliente) {
            return response()->json(['erro' => 'Cliente não encontrado.'], 422);
        }

        try {
            $op = PosOperacao::create(['empresa_id' => $empresaId, 'user_id' => $user->id, 'chave' => $request->chave, 'tipo' => 'pedido']);
        } catch (UniqueConstraintViolationException) {
            $existente = PosOperacao::where('empresa_id', $empresaId)->where('chave', $request->chave)->first();
            if ($existente?->pedido_id) {
                return $this->resposta($this->pedidoDaEmpresa($existente->pedido_id), $request, true);
            }

            return response()->json(['erro' => 'Pedido em processamento. Aguarde.', 'codigo' => 'em_processamento'], 409);
        }

        $produtos = Produto::withoutGlobalScopes()->where('empresa_id', $empresaId)->where('status', 'ativo')
            ->whereIn('id', collect($request->itens)->pluck('produto_id'))->get()->keyBy('id');

        try {
            $pedido = DB::transaction(function () use ($request, $empresaId, $produtos, $cliente, $user) {
                $numero = (int) Pedido::withoutGlobalScopes()->where('empresa_id', $empresaId)->lockForUpdate()->max('numero') + 1;

                $subtotal = 0.0;
                $itens = [];
                foreach ($request->itens as $item) {
                    $p = $produtos[$item['produto_id']] ?? null;
                    if (! $p) {
                        throw new \DomainException('Produto indisponível no pedido (id ' . $item['produto_id'] . ').');
                    }
                    $qtd = (float) $item['quantidade'];
                    $preco = (float) $p->preco_venda;
                    $perc = (float) ($item['desconto_percentual'] ?? 0);
                    $desc = round($preco * $qtd * ($perc / 100), 2);
                    $total = round($preco * $qtd - $desc, 2);
                    $itens[] = [
                        'produto_id' => $p->id,
                        'descricao' => $p->descricao,
                        'quantidade' => $qtd,
                        'preco_unitario' => $preco,
                        'desconto_percentual' => $perc,
                        'desconto_valor' => $desc,
                        'total' => $total,
                    ];
                    $subtotal += $total;
                }

                $desconto = min(round((float) $request->input('desconto_valor', 0), 2), $subtotal);

                $pedido = Pedido::create([
                    'empresa_id' => $empresaId,
                    'unidade_id' => (int) session('unidade_id'),
                    'cliente_id' => $cliente->id,
                    'vendedor_id' => $user->id,
                    'numero' => $numero,
                    'condicao_pagamento' => $request->condicao_pagamento,
                    'canal' => CanalVenda::Presencial->value,
                    'subtotal' => round($subtotal, 2),
                    'desconto_percentual' => 0,
                    'desconto_valor' => $desconto,
                    'total' => round($subtotal - $desconto, 2),
                    'status' => StatusPedido::Rascunho,
                    'observacoes_externas' => $request->observacoes,
                    'observacoes_internas' => 'Emitido no app POS',
                ]);
                foreach ($itens as $i) {
                    $pedido->itens()->create($i);
                }

                return $pedido;
            });
        } catch (\DomainException $e) {
            $op->delete();

            return response()->json(['erro' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $op->delete();
            throw $e;
        }

        $op->update(['status' => 'concluida', 'pedido_id' => $pedido->id]);

        if ($request->boolean('confirmar')) {
            app(PedidoController::class)->updateStatus(new Request(['status' => 'confirmado']), $pedido);
            $pedido->refresh();
        }

        $this->evento($request, 'pedido_emitido', "Pedido #{$pedido->numero} R$ " . number_format((float) $pedido->total, 2, ',', '.'), ['pedido_id' => $pedido->id]);

        return $this->resposta($pedido, $request);
    }

    /** GET pedidos?q=&status= — pedidos da loja (vendedor vê os próprios). */
    public function listar(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string']]);
        $user = $request->user();

        $query = Pedido::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', (int) session('empresa_id'))
            ->where('unidade_id', (int) session('unidade_id'))
            ->with('cliente:id,nome_razao_social')
            ->latest('id');

        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', $request->status));
        }
        if ($q = trim((string) $request->q)) {
            $query->where(function ($w) use ($q) {
                $w->where('numero', ctype_digit($q) ? (int) $q : -1)
                    ->orWhereHas('cliente', fn ($c) => $c->withoutGlobalScopes()->where('nome_razao_social', 'like', "%{$q}%"));
            });
        }
        if (! PosPermissoes::pode($user, 'vendas', 'excluir') && ! PosPermissoes::pode($user, 'pedidos', 'editar')) {
            $query->where('vendedor_id', $user->id);
        }

        return response()->json(['pedidos' => $query->limit(100)->get()->map(fn (Pedido $p) => $this->resumo($p))]);
    }

    public function mostrar(Request $request, int $id): JsonResponse
    {
        return $this->resposta($this->pedidoDaEmpresa($id), $request, true);
    }

    /**
     * Recebe o pedido no caixa. O app já cobrou o TOTAL do pedido na Stone
     * (ou recebeu em dinheiro). Rascunho é confirmado antes de faturar.
     */
    public function faturar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'chave' => ['required', 'string', 'min:8', 'max:64'],
            'documento' => ['nullable', 'in:recibo,cupom_fiscal'],
            'pagamento.forma' => ['required', 'in:dinheiro,pix,cartao_debito,cartao_credito'],
            'pagamento.parcelas' => ['nullable', 'integer', 'min:1', 'max:24'],
            'pagamento.stone' => ['nullable', 'array'],
            'pagamento.stone.valor_centavos' => ['nullable', 'integer', 'min:1'],
            'colunas' => ['nullable', 'integer', 'min:24', 'max:48'],
        ]);
        $user = $request->user();
        if (! (PosPermissoes::pode($user, 'pedidos', 'editar') && PosPermissoes::pode($user, 'vendas', 'criar'))) {
            return response()->json(['erro' => 'Seu perfil não recebe pedidos no caixa.'], 403);
        }

        $pedido = $this->pedidoDaEmpresa($id);
        $empresaId = (int) session('empresa_id');
        $forma = $request->input('pagamento.forma');
        $stone = $request->input('pagamento.stone');
        $eletronico = in_array($forma, PosStone::FORMAS_ELETRONICAS, true);

        try {
            $op = PosOperacao::create(['empresa_id' => $empresaId, 'user_id' => $user->id, 'chave' => $request->chave, 'tipo' => 'faturar_pedido', 'pedido_id' => $pedido->id]);
        } catch (UniqueConstraintViolationException) {
            $existente = PosOperacao::where('empresa_id', $empresaId)->where('chave', $request->chave)->first();
            if ($existente?->venda_id) {
                return app(PosVendaController::class)->mostrar($request, (int) $existente->venda_id);
            }

            return response()->json(['erro' => 'Recebimento em processamento. Aguarde.', 'codigo' => 'em_processamento'], 409);
        }

        $falha = function (string $msg, int $status = 422) use ($op, $eletronico, $request, $pedido) {
            $op->delete();
            $this->evento($request, 'erro_faturar_pedido', $msg, ['pedido_id' => $pedido->id]);

            return response()->json(['erro' => $msg, 'estornar' => $eletronico], $status);
        };

        if (! in_array($pedido->status->value, ['rascunho', 'confirmado'], true)) {
            return $falha("Pedido {$pedido->status->label()} não pode ser recebido.");
        }
        $totalCentavos = (int) round(((float) $pedido->total) * 100);
        if ($eletronico && (int) ($stone['valor_centavos'] ?? 0) !== $totalCentavos) {
            return $falha('Valor cobrado na Stone diferente do total do pedido.', 409);
        }

        if ($pedido->status === StatusPedido::Rascunho) {
            app(PedidoController::class)->updateStatus(new Request(['status' => 'confirmado']), $pedido);
            $pedido->refresh();
            if ($pedido->status !== StatusPedido::Confirmado) {
                return $falha(session()->get('error') ?: 'Não foi possível confirmar o pedido.');
            }
        }

        $parcelas = $forma === 'cartao_credito' ? max(1, (int) $request->input('pagamento.parcelas', 1)) : null;
        $sub = new Request(array_filter(['documento' => 'nenhum', 'forma_pagamento' => $forma, 'parcelas' => $parcelas]));
        $sub->setLaravelSession($request->session());
        $sub->setUserResolver(fn () => $user);
        app(PedidoController::class)->faturar($sub, $pedido);

        $venda = Venda::withoutGlobalScope(UnidadeScope::class)->where('pedido_id', $pedido->id)->latest('id')->first();
        if (! $venda || session()->get('error')) {
            return $falha(session()->get('error') ?: 'Não foi possível receber o pedido.');
        }

        // Dados da Stone na venda (conciliação + grupo de cartão da NFC-e).
        $venda->update(['pagamento_detalhes' => [PosStone::pagamento($forma, (float) $venda->total, $parcelas, $eletronico ? $stone : null)]]);
        $op->update(['status' => 'concluida', 'venda_id' => $venda->id]);

        $nfceErro = null;
        if ($request->input('documento') === 'cupom_fiscal') {
            try {
                $config = ConfiguracaoFiscal::withoutGlobalScopes()->where('empresa_id', $venda->empresa_id)->where('unidade_id', $venda->unidade_id)->first();
                if (! $config?->emissao_fiscal_ativa || ! $config->emite_nfce) {
                    throw new \DomainException('Emissão de NFC-e não está ativa nesta loja.');
                }
                (new NFCeService(FocusNFeClient::fromConfig($config)))->emitir($venda->load(['itens.produto', 'cliente', 'empresa']), $config);
            } catch (\Throwable $e) {
                $nfceErro = $e->getMessage();
                Log::error('[POS] NFC-e do pedido falhou', ['venda_id' => $venda->id, 'erro' => $nfceErro]);
            }
        }

        $this->evento($request, 'pedido_faturado', "Pedido #{$pedido->numero} recebido", ['pedido_id' => $pedido->id, 'venda_id' => $venda->id, 'atk' => $stone['atk'] ?? null]);

        $resposta = app(PosVendaController::class)->mostrar($request, (int) $venda->id);
        if ($nfceErro) {
            $dados = $resposta->getData(true);
            $dados['nfce_erro'] = $nfceErro;
            $resposta->setData($dados);
        }

        return $resposta;
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $pedido = $this->pedidoDaEmpresa($id);
        if (! PosPermissoes::pode($request->user(), 'pedidos', 'editar')) {
            return response()->json(['erro' => 'Seu perfil não cancela pedidos.'], 403);
        }
        if (! in_array($pedido->status->value, ['rascunho', 'confirmado'], true)) {
            return response()->json(['erro' => 'Só pedido em rascunho ou confirmado pode ser cancelado aqui. Pedido faturado: cancele a venda.'], 422);
        }

        app(PedidoController::class)->updateStatus(new Request(['status' => 'cancelado']), $pedido);
        $pedido->refresh();
        if ($pedido->status !== StatusPedido::Cancelado) {
            return response()->json(['erro' => session()->get('error') ?: 'Não foi possível cancelar o pedido.'], 422);
        }
        $this->evento($request, 'pedido_cancelado', "Pedido #{$pedido->numero}", ['pedido_id' => $pedido->id]);

        return $this->resposta($pedido, $request);
    }

    private function pedidoDaEmpresa(int $id): Pedido
    {
        return Pedido::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', (int) session('empresa_id'))
            ->where('unidade_id', (int) session('unidade_id'))
            ->findOrFail($id);
    }

    private function resumo(Pedido $p): array
    {
        return [
            'id' => $p->id,
            'numero' => $p->numero,
            'status' => $p->status->value,
            'status_nome' => $p->status->label(),
            'total' => (float) $p->total,
            'cliente' => $p->cliente?->nome_razao_social,
            'criado_em' => $p->created_at->toIso8601String(),
        ];
    }

    private function resposta(Pedido $pedido, Request $request, bool $repetida = false): JsonResponse
    {
        $pedido->load(['itens', 'cliente']);

        return response()->json([
            'pedido' => $this->resumo($pedido) + [
                'condicao_pagamento' => $pedido->condicao_pagamento,
                'subtotal' => (float) $pedido->subtotal,
                'desconto' => (float) $pedido->desconto_valor,
                'total_centavos' => (int) round(((float) $pedido->total) * 100),
                'itens' => $pedido->itens->map(fn ($i) => [
                    'produto_id' => $i->produto_id,
                    'descricao' => $i->descricao,
                    'quantidade' => (float) $i->quantidade,
                    'preco_unitario' => (float) $i->preco_unitario,
                    'total' => (float) $i->total,
                ]),
            ],
            'cupom' => (new PosCupomService((int) $request->input('colunas', 32)))->pedido($pedido),
            'repetida' => $repetida,
        ]);
    }

    private function evento(Request $request, string $tipo, string $mensagem, array $dados = []): void
    {
        PosEvento::create([
            'empresa_id' => (int) session('empresa_id'),
            'user_id' => $request->user()?->id,
            'unidade_id' => session('unidade_id'),
            'dispositivo_serial' => $request->attributes->get('pos_token')?->dispositivo_serial,
            'tipo' => $tipo,
            'mensagem' => mb_substr($mensagem, 0, 500),
            'dados' => $dados,
            'ocorrido_em' => now(),
        ]);
    }
}
