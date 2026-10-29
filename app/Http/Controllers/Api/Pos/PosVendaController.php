<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\StatusCaixa;
use App\Enums\StatusNotaFiscal;
use App\Enums\StatusVenda;
use App\Http\Controllers\App\PdvController;
use App\Http\Controllers\App\VendaController;
use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\ConfiguracaoFiscal;
use App\Models\ConfiguracaoLoja;
use App\Models\Devolucao;
use App\Models\NotaFiscal;
use App\Models\PosEvento;
use App\Models\PosOperacao;
use App\Models\Produto;
use App\Models\Venda;
use App\Scopes\UnidadeScope;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFCeService;
use App\Services\Pos\PosCupomService;
use App\Services\Pos\PosPermissoes;
use App\Services\Pos\PosStone;
use App\Services\TabelaPrecoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Venda no POS Stone — a regra é a do PDV web (PdvController::registrarVenda),
 * chamada com o contexto do operador. Fluxo do app:
 *
 *   1. POST vendas/cotar      → total EXATO a cobrar (tabela de preço da forma,
 *                               acréscimo por parte, juros do parcelamento).
 *   2. Stone (Deeplink)       → cobra esse total na maquininha.
 *   3. POST vendas            → registra com os dados da Stone (ATK, autorização,
 *                               bandeira). Idempotente pela `chave` do app.
 *      - 409/422 com `estornar=true`: o ERP não aceitou → o app estorna na Stone.
 *      - 5xx/sem rede: o app reenvia com a MESMA chave (nunca cria duas vendas).
 *
 * A cotação e a conferência do valor rodam o próprio registrarVenda dentro de
 * uma transação desfeita (sem NFC-e): o número que o operador vê é o que o ERP grava.
 */
class PosVendaController extends Controller
{
    private function regrasVenda(): array
    {
        return [
            'itens' => ['required', 'array', 'min:1', 'max:200'],
            'itens.*.produto_id' => ['required', 'integer'],
            'itens.*.quantidade' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'itens.*.desconto_valor' => ['nullable', 'numeric', 'min:0'],
            'cliente_id' => ['nullable', 'integer'],
            'cpf_cnpj_nota' => ['nullable', 'string', 'max:18'],
            'desconto_valor' => ['nullable', 'numeric', 'min:0'],
            'desconto_percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pagamento.forma' => ['required', 'in:dinheiro,pix,cartao_debito,cartao_credito'],
            'pagamento.parcelas' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }

    public function cotar(Request $request, TabelaPrecoService $tabelas): JsonResponse
    {
        $request->validate($this->regrasVenda());
        if ($erro = $this->exigeVender($request)) {
            return $erro;
        }

        $base = $this->valorBase($request, $tabelas);
        $forma = $request->input('pagamento.forma');
        $parcelas = $forma === 'cartao_credito' ? max(1, (int) $request->input('pagamento.parcelas', 1)) : null;

        $sim = $this->simular($request, PosStone::pagamento($forma, $base, $parcelas, null));
        if (isset($sim['erro'])) {
            return response()->json(['erro' => $sim['erro']], $sim['status']);
        }

        $venda = $sim['venda'];
        $pg = $venda['pagamento_detalhes'][0] ?? [];

        return response()->json([
            'valor_base' => $base,
            'total' => (float) $venda['total'],
            'total_centavos' => (int) round(((float) $venda['total']) * 100),
            'subtotal' => (float) $venda['subtotal'],
            'desconto' => (float) $venda['desconto_valor'],
            'parcelas' => $parcelas ?? 1,
            'valor_parcela' => isset($pg['valor_parcela']) ? (float) $pg['valor_parcela'] : null,
            'juros' => (float) ($pg['juros_valor'] ?? 0),
            'acrescimo' => (float) ($pg['acrescimo_forma_valor'] ?? 0),
            'itens' => collect($venda['itens'])->map(fn ($i) => [
                'produto_id' => $i['produto_id'],
                'descricao' => $i['descricao'],
                'quantidade' => (float) $i['quantidade'],
                'preco_unitario' => (float) $i['preco_unitario'],
                'desconto_valor' => (float) $i['desconto_valor'],
                'total' => (float) $i['total'],
            ])->values(),
        ]);
    }

    public function registrar(Request $request, TabelaPrecoService $tabelas): JsonResponse
    {
        $request->validate($this->regrasVenda() + [
            'chave' => ['required', 'string', 'min:8', 'max:64'],
            'pagamento.valor_base' => ['required', 'numeric', 'min:0.01'],
            'pagamento.valor_recebido' => ['nullable', 'numeric', 'min:0.01'],
            'pagamento.stone' => ['nullable', 'array'],
            'pagamento.stone.atk' => ['nullable', 'string', 'max:60'],
            'pagamento.stone.itk' => ['nullable', 'string', 'max:60'],
            'pagamento.stone.autorizacao' => ['nullable', 'string', 'max:20'],
            'pagamento.stone.bandeira' => ['nullable', 'string', 'max:30'],
            'pagamento.stone.tipo' => ['nullable', 'string', 'max:30'],
            'pagamento.stone.valor_centavos' => ['nullable', 'integer', 'min:1'],
            'pagamento.stone.data_hora' => ['nullable', 'string', 'max:40'],
            'documento' => ['nullable', 'in:recibo,cupom_fiscal'],
            'colunas' => ['nullable', 'integer', 'min:24', 'max:48'],
        ]);
        if ($erro = $this->exigeVender($request)) {
            return $erro;
        }

        $forma = $request->input('pagamento.forma');
        $eletronico = in_array($forma, PosStone::FORMAS_ELETRONICAS, true);
        $stone = $request->input('pagamento.stone');
        if ($eletronico && empty($stone['valor_centavos'])) {
            return response()->json(['erro' => 'Pagamento eletrônico sem o retorno da Stone.', 'estornar' => false], 422);
        }

        // Idempotência: a mesma chave nunca gera duas vendas.
        $empresaId = (int) session('empresa_id');
        try {
            $op = PosOperacao::create([
                'empresa_id' => $empresaId,
                'user_id' => $request->user()->id,
                'chave' => $request->chave,
                'tipo' => 'venda',
            ]);
        } catch (UniqueConstraintViolationException) {
            $existente = PosOperacao::where('empresa_id', $empresaId)->where('chave', $request->chave)->first();
            if ($existente?->status === 'concluida' && $existente->venda_id) {
                return $this->respostaVenda(Venda::withoutGlobalScope(UnidadeScope::class)->find($existente->venda_id), $request, null, true);
            }

            return response()->json(['erro' => 'Esta venda ainda está sendo registrada. Aguarde e tente de novo.', 'codigo' => 'em_processamento'], 409);
        }

        $parcelas = $forma === 'cartao_credito' ? max(1, (int) $request->input('pagamento.parcelas', 1)) : null;
        $valorBase = round((float) $request->input('pagamento.valor_base'), 2);
        $valor = $forma === 'dinheiro'
            ? round((float) $request->input('pagamento.valor_recebido', $valorBase), 2)
            : $valorBase;
        $pagamento = PosStone::pagamento($forma, $valor, $parcelas, $eletronico ? $stone : null);

        // O que passou na maquininha tem de ser o total que o ERP vai gravar.
        if ($eletronico) {
            $sim = $this->simular($request, $pagamento);
            if (isset($sim['erro'])) {
                $op->delete();
                $this->evento($request, 'erro_registro_venda', $sim['erro'], ['chave' => $request->chave, 'stone' => $stone]);

                return response()->json(['erro' => $sim['erro'], 'estornar' => $sim['status'] < 500], $sim['status']);
            }
            $totalCentavos = (int) round(((float) $sim['venda']['total']) * 100);
            if ($totalCentavos !== (int) $stone['valor_centavos']) {
                $op->delete();
                $msg = 'Valor cobrado na Stone (R$ ' . number_format($stone['valor_centavos'] / 100, 2, ',', '.')
                    . ') diferente do total da venda (R$ ' . number_format($totalCentavos / 100, 2, ',', '.') . ').';
                $this->evento($request, 'valor_divergente', $msg, ['chave' => $request->chave, 'stone' => $stone]);

                return response()->json(['erro' => $msg, 'estornar' => true, 'total_centavos' => $totalCentavos], 409);
            }
        }

        $resposta = app(PdvController::class)->registrarVenda($this->subRequest($request, $pagamento, $request->input('documento')));
        $dados = $resposta->getData(true);

        if ($resposta->getStatusCode() !== 200 || empty($dados['success'])) {
            $op->delete();
            $erro = $dados['error'] ?? $dados['message'] ?? 'Não foi possível registrar a venda.';
            $this->evento($request, 'erro_registro_venda', $erro, ['chave' => $request->chave, 'stone' => $stone]);

            return response()->json([
                'erro' => $erro,
                'estornar' => $eletronico && $resposta->getStatusCode() < 500,
            ], $resposta->getStatusCode() === 200 ? 422 : $resposta->getStatusCode());
        }

        $venda = Venda::withoutGlobalScope(UnidadeScope::class)->find($dados['venda']['id']);
        $op->update(['status' => 'concluida', 'venda_id' => $venda->id]);
        $this->evento($request, 'venda_registrada', "Venda #{$venda->numero} R$ " . number_format((float) $venda->total, 2, ',', '.'), [
            'venda_id' => $venda->id, 'forma' => $forma, 'atk' => $stone['atk'] ?? null,
        ]);

        return $this->respostaVenda($venda, $request, $dados['nfce_erro'] ?? null);
    }

    /** GET vendas?escopo=caixa|hoje — vendas do operador para reimpressão e cancelamento. */
    public function listar(Request $request): JsonResponse
    {
        $query = Venda::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', (int) session('empresa_id'))
            ->where('unidade_id', (int) session('unidade_id'))
            ->with('cliente:id,nome_razao_social')
            ->latest('id');

        if ($request->input('escopo', 'caixa') === 'caixa' && session('caixa_id')) {
            $query->where('caixa_id', (int) session('caixa_id'));
        } else {
            $query->whereDate('created_at', today());
        }
        if (! PosPermissoes::pode($request->user(), 'vendas', 'excluir')) {
            $query->where('vendedor_id', $request->user()->id);
        }

        return response()->json(['vendas' => $query->limit(100)->get()->map(fn (Venda $v) => $this->vendaResumo($v))]);
    }

    public function mostrar(Request $request, int $id): JsonResponse
    {
        $venda = $this->vendaDaLoja($id);

        return $this->respostaVenda($venda, $request, null, true);
    }

    /**
     * Antes de estornar na Stone: a venda pode ser cancelada? Devolve o que
     * tem de ser estornado na maquininha (por ATK). Nada é alterado aqui.
     */
    public function validarCancelamento(Request $request, int $id): JsonResponse
    {
        $venda = $this->vendaDaLoja($id);
        if ($motivo = $this->impedimentoCancelar($request, $venda)) {
            return response()->json(['erro' => $motivo], 422);
        }

        return response()->json([
            'pode_cancelar' => true,
            'tem_nfce' => (bool) $this->nfceAutorizada($venda),
            'estornos' => collect($venda->pagamento_detalhes ?? [])
                ->filter(fn ($pg) => in_array($pg['forma'] ?? '', PosStone::FORMAS_ELETRONICAS, true))
                ->map(fn ($pg) => [
                    'forma' => $pg['forma'],
                    'valor_centavos' => (int) ($pg['stone_valor_centavos'] ?? round(((float) $pg['valor']) * 100)),
                    'atk' => $pg['stone_atk'] ?? null,
                ])->values(),
        ]);
    }

    /**
     * Cancela a venda no ERP DEPOIS do estorno na Stone: NFC-e (se autorizada)
     * e então o cancelamento do VendaController (estoque, caixa, contas a receber).
     */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:200'],
            'estornos' => ['nullable', 'array'],
        ]);
        $venda = $this->vendaDaLoja($id);
        if ($venda->status === StatusVenda::Cancelada) {
            return $this->respostaVenda($venda, $request, null, true);
        }
        if ($motivo = $this->impedimentoCancelar($request, $venda)) {
            return response()->json(['erro' => $motivo], 422);
        }

        if ($nota = $this->nfceAutorizada($venda)) {
            try {
                $config = ConfiguracaoFiscal::withoutGlobalScopes()
                    ->where('empresa_id', $venda->empresa_id)->where('unidade_id', $venda->unidade_id)->first();
                (new NFCeService(FocusNFeClient::fromConfig($config)))
                    ->cancelar($nota, mb_substr('Cancelamento no caixa: ' . $request->motivo, 0, 255));
            } catch (\Throwable $e) {
                $this->evento($request, 'erro_cancelar_nfce', $e->getMessage(), ['venda_id' => $venda->id]);

                return response()->json(['erro' => 'A NFC-e não foi cancelada na SEFAZ: ' . $e->getMessage() . ' Cancele a nota pelo ERP.'], 422);
            }
        }

        $resp = app(VendaController::class)->destroy($venda);
        $erro = session()->get('error');
        $venda->refresh();
        if ($erro || $venda->status !== StatusVenda::Cancelada) {
            $this->evento($request, 'erro_cancelar_venda', $erro ?: 'Cancelamento não concluído', ['venda_id' => $venda->id]);

            return response()->json(['erro' => $erro ?: 'Não foi possível cancelar a venda.'], 422);
        }

        $this->evento($request, 'venda_cancelada', "Venda #{$venda->numero}: {$request->motivo}", [
            'venda_id' => $venda->id, 'estornos' => $request->input('estornos', []),
        ]);

        return $this->respostaVenda($venda, $request, null, true);
    }

    // ─── Internos ───

    /** Soma dos itens na tabela de preço da forma, já com os descontos: o `valor` que o PDV espera. */
    private function valorBase(Request $request, TabelaPrecoService $tabelas): float
    {
        $empresaId = (int) session('empresa_id');
        $config = ConfiguracaoLoja::daUnidade($empresaId, (int) session('unidade_id'));
        $cliente = $request->cliente_id ? Cliente::withoutGlobalScopes()->where('empresa_id', $empresaId)->find($request->cliente_id) : null;
        $modalidade = $tabelas->modalidadeDaVenda([$request->input('pagamento.forma')], $config, $cliente);

        $produtos = Produto::withoutGlobalScopes()->where('empresa_id', $empresaId)
            ->whereIn('id', collect($request->itens)->pluck('produto_id'))
            ->with('precos:id,produto_id,modalidade,valor')->get()->keyBy('id');

        $subtotal = 0.0;
        foreach ($request->itens as $item) {
            $p = $produtos[$item['produto_id']] ?? null;
            if (! $p) {
                continue;
            }
            $preco = $tabelas->precosDoProduto($p, $config)[$modalidade];
            $subtotal += round($preco * (float) $item['quantidade'] - (float) ($item['desconto_valor'] ?? 0), 2);
        }

        $desconto = (float) $request->input('desconto_valor', 0);
        if ($desconto == 0 && (float) $request->input('desconto_percentual', 0) > 0) {
            $desconto = round($subtotal * ((float) $request->desconto_percentual / 100), 2);
        }

        return max(0.01, round($subtotal - $desconto, 2));
    }

    /** Roda o registrarVenda numa transação desfeita, sem documento fiscal. */
    private function simular(Request $request, array $pagamento): array
    {
        DB::beginTransaction();
        try {
            $resposta = app(PdvController::class)->registrarVenda($this->subRequest($request, $pagamento, 'recibo'));
        } finally {
            DB::rollBack();
        }

        $dados = $resposta->getData(true);
        if ($resposta->getStatusCode() !== 200 || empty($dados['success'])) {
            return ['erro' => $dados['error'] ?? $dados['message'] ?? 'Venda inválida.', 'status' => $resposta->getStatusCode() === 200 ? 422 : $resposta->getStatusCode()];
        }

        return ['venda' => $dados['venda']];
    }

    /** Request no formato do PDV web, com a mesma sessão/usuário da request do POS. */
    private function subRequest(Request $request, array $pagamento, ?string $documento): Request
    {
        $empresaId = (int) session('empresa_id');
        $produtos = Produto::withoutGlobalScopes()->where('empresa_id', $empresaId)
            ->whereIn('id', collect($request->itens)->pluck('produto_id'))->pluck('preco_venda', 'id');

        $itens = collect($request->itens)
            // Produto de outra empresa nunca entra (o PDV valida só `exists`).
            ->filter(fn ($i) => $produtos->has($i['produto_id']))
            ->map(fn ($i) => [
                'produto_id' => (int) $i['produto_id'],
                'quantidade' => (float) $i['quantidade'],
                'preco_unitario' => (float) $produtos[$i['produto_id']], // o servidor reprecifica (tabela_precos)
                'desconto_valor' => (float) ($i['desconto_valor'] ?? 0),
            ])->values()->all();

        $clienteId = null;
        if ($request->cliente_id) {
            $clienteId = Cliente::withoutGlobalScopes()->where('empresa_id', $empresaId)->whereKey($request->cliente_id)->value('id');
        }

        $sub = Request::create('/app/pdv/venda', 'POST', array_filter([
            'itens' => $itens,
            'pagamentos' => [$pagamento],
            'cliente_id' => $clienteId,
            'cpf_cnpj_nota' => $request->cpf_cnpj_nota,
            'desconto_valor' => $request->desconto_valor,
            'desconto_percentual' => $request->desconto_percentual,
            'vendedor_id' => $request->user()->id,
            'tabela_precos' => true,
            'juros_parcelamento' => true,
            'documento' => $documento,
        ], fn ($v) => $v !== null && $v !== ''));
        $sub->headers->set('Accept', 'application/json');
        $sub->setLaravelSession($request->session());
        $sub->setUserResolver(fn () => $request->user());

        return $sub;
    }

    private function respostaVenda(Venda $venda, Request $request, ?string $nfceErro, bool $repetida = false): JsonResponse
    {
        $nota = $venda->notasFiscais()->where('tipo', 'nfce')->latest()->first();
        $cupom = (new PosCupomService((int) $request->input('colunas', 32)))->venda($venda, $nota);

        return response()->json([
            'venda' => $this->vendaResumo($venda) + [
                'pagamentos' => $venda->pagamento_detalhes,
                'troco' => (float) $venda->troco,
            ],
            'nfce' => $nota ? [
                'status' => $nota->status->value,
                'numero' => $nota->numero,
                'chave' => $nota->chave_acesso,
                'mensagem' => $nota->focus_mensagem,
            ] : null,
            'nfce_erro' => $nfceErro,
            'cupom' => $cupom,
            'repetida' => $repetida,
        ]);
    }

    private function vendaResumo(Venda $v): array
    {
        return [
            'id' => $v->id,
            'numero' => $v->numero,
            'total' => (float) $v->total,
            'status' => $v->status->value,
            'forma_pagamento' => $v->forma_pagamento,
            'cliente' => $v->cliente?->nome_razao_social,
            'criada_em' => $v->created_at->toIso8601String(),
            'caixa_id' => $v->caixa_id,
        ];
    }

    private function vendaDaLoja(int $id): Venda
    {
        return Venda::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', (int) session('empresa_id'))
            ->where('unidade_id', (int) session('unidade_id'))
            ->findOrFail($id);
    }

    private function nfceAutorizada(Venda $venda): ?NotaFiscal
    {
        return $venda->notasFiscais()->where('tipo', 'nfce')->where('status', StatusNotaFiscal::Autorizada)->latest()->first();
    }

    /** Mesmas travas do VendaController::destroy + quem pode cancelar no POS. */
    private function impedimentoCancelar(Request $request, Venda $venda): ?string
    {
        $user = $request->user();
        if ($venda->status === StatusVenda::Cancelada) {
            return 'Esta venda já está cancelada.';
        }

        if (! PosPermissoes::pode($user, 'vendas', 'excluir')) {
            // Vendedor/caixa: só desfaz a PRÓPRIA venda, com o caixa dela ainda aberto.
            $caixa = Caixa::withoutGlobalScope(UnidadeScope::class)->find($venda->caixa_id);
            $propria = (int) $venda->vendedor_id === (int) $user->id || (int) $caixa?->user_id === (int) $user->id;
            if (! $propria || ! $caixa || $caixa->status !== StatusCaixa::Aberto) {
                return 'Só o gerente cancela esta venda. Você pode cancelar apenas as suas vendas do caixa aberto.';
            }
        }

        $temTroca = Devolucao::withoutGlobalScope(UnidadeScope::class)
            ->where(fn ($q) => $q->where('venda_id', $venda->id)->orWhere('venda_nova_id', $venda->id))
            ->where('status', '!=', 'cancelada')->exists();
        if ($temTroca) {
            return 'Esta venda tem troca/devolução registrada e não pode ser cancelada por inteiro.';
        }

        $notaPendente = $venda->notasFiscais()->whereIn('status', ['pendente', 'contingencia'])->exists();
        if ($notaPendente) {
            return 'A NFC-e desta venda ainda está em processamento. Aguarde alguns minutos.';
        }

        if (! session('caixa_id')) {
            $caixaVenda = Caixa::withoutGlobalScope(UnidadeScope::class)->find($venda->caixa_id);
            if (! $caixaVenda || $caixaVenda->status !== StatusCaixa::Aberto) {
                return 'Abra o seu caixa para cancelar: o valor da venda sai dele.';
            }
        }

        return null;
    }

    private function exigeVender(Request $request): ?JsonResponse
    {
        if (! PosPermissoes::pode($request->user(), 'vendas', 'criar')) {
            return response()->json(['erro' => 'Seu perfil não registra vendas.'], 403);
        }
        if (! session('caixa_id')) {
            return response()->json(['erro' => 'Abra o caixa antes de vender.', 'codigo' => 'caixa_fechado'], 409);
        }

        return null;
    }

    private function evento(Request $request, string $tipo, string $mensagem, array $dados = []): void
    {
        try {
            $token = $request->attributes->get('pos_token');
            PosEvento::create([
                'empresa_id' => (int) session('empresa_id'),
                'user_id' => $request->user()?->id,
                'unidade_id' => session('unidade_id'),
                'dispositivo_serial' => $token?->dispositivo_serial,
                'tipo' => $tipo,
                'mensagem' => mb_substr($mensagem, 0, 500),
                'dados' => $dados,
                'ocorrido_em' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[POS] evento não gravado', ['tipo' => $tipo, 'erro' => $e->getMessage()]);
        }
    }
}
