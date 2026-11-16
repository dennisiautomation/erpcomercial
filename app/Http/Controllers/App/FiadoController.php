<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\FiadoRecebimento;
use App\Models\Venda;
use App\Scopes\UnidadeScope;
use App\Services\FiadoService;
use App\Services\FormaPagamentoService;
use App\Services\LancamentoCaixaService;
use Illuminate\Http\Request;

/**
 * Fiado / pagamento direto com a loja (16/11/2026).
 *
 * - "Quem me deve": clientes com fiado em aberto (Vendas → Fiado).
 * - Ficha do cliente: o que deve, cada compra, cada pagamento; botão Receber.
 * - Receber: no PDV (modal) ou na ficha — sempre entra no caixa aberto de quem recebe.
 */
class FiadoController extends Controller
{
    public function __construct(private FiadoService $fiado)
    {
    }

    private function empresaId(): int
    {
        return (int) (session('empresa_id') ?? auth()->user()->empresa_id);
    }

    public function index(Request $request)
    {
        $empresaId = $this->empresaId();
        $devedores = $this->fiado->devedores($empresaId, $request->input('busca'), $request->boolean('vencidos'));

        return view('app.fiado.index', [
            'devedores'  => $devedores,
            'totalAberto'  => round((float) $devedores->sum('devendo'), 2),
            'totalVencido' => round((float) $devedores->sum('vencido'), 2),
            'nomeForma'  => $this->nomeDaForma($empresaId),
        ]);
    }

    public function cliente(int $cliente)
    {
        $empresaId = $this->empresaId();
        $cli = Cliente::where('empresa_id', $empresaId)->findOrFail($cliente);

        $contas = ContaReceber::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $empresaId)
            ->where('cliente_id', $cli->id)
            ->where('forma_pagamento', FiadoService::FORMA)
            ->where('status', '!=', 'cancelada')
            ->with(['venda' => fn ($q) => $q->withoutGlobalScope(UnidadeScope::class)])
            ->orderByRaw("status = 'paga'")
            ->orderBy('vencimento')
            ->orderBy('id')
            ->limit(300)
            ->get();

        $recebimentos = FiadoRecebimento::where('empresa_id', $empresaId)
            ->where('cliente_id', $cli->id)
            ->with('usuario')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $caixa = app(LancamentoCaixaService::class)->caixaAbertoDoUsuario((int) session('unidade_id'), (int) auth()->id());

        return view('app.fiado.cliente', [
            'cliente'      => $cli,
            'saldo'        => $this->fiado->saldo($empresaId, $cli->id),
            'contas'       => $contas,
            'recebimentos' => $recebimentos,
            'formas'       => $this->fiado->formasDeRecebimento($empresaId),
            'caixa'        => $caixa,
            'podeReceber'  => \App\Http\Middleware\CheckPermission::canUser(auth()->user(), 'vendas', 'criar'),
            'nomeForma'    => $this->nomeDaForma($empresaId),
        ]);
    }

    /** Ficha resumida para o PDV (saldo + contas em aberto). */
    public function situacao(int $cliente)
    {
        $empresaId = $this->empresaId();
        $cli = Cliente::where('empresa_id', $empresaId)->findOrFail($cliente);
        $hoje = now()->startOfDay();

        return response()->json([
            'cliente' => ['id' => $cli->id, 'nome' => $cli->nome_razao_social, 'cpf_cnpj' => $cli->cpf_cnpj],
            'saldo'   => $this->fiado->saldo($empresaId, $cli->id),
            'contas'  => $this->fiado->contasAbertas($empresaId, $cli->id)->map(fn ($c) => [
                'id'         => $c->id,
                'descricao'  => $c->descricao,
                'vencimento' => $c->vencimento?->format('d/m/Y'),
                'vencida'    => $c->vencimento && $c->vencimento->lt($hoje),
                'valor'      => (float) $c->valor,
                'aberto'     => round((float) $c->valor - (float) $c->valor_pago, 2),
            ])->values(),
        ]);
    }

    /**
     * Recebe o fiado. Do PDV vem JSON (caixa da sessão); da ficha do cliente vem
     * formulário (caixa aberto do usuário na loja da sessão).
     */
    public function receber(Request $request, int $cliente)
    {
        $request->validate([
            'valor'       => 'required|numeric|min:0.01',
            'chave'       => 'required|string|max:40',
            'conta_ids'   => 'nullable|array',
            'conta_ids.*' => 'integer',
            'observacoes' => 'nullable|string|max:255',
        ]);
        $empresaId = $this->empresaId();
        $json = $request->expectsJson();
        $erro = function (string $msg) use ($json) {
            return $json ? response()->json(['error' => $msg], 422) : back()->withInput()->with('error', $msg);
        };

        $cli = Cliente::where('empresa_id', $empresaId)->find($cliente);
        if (! $cli) {
            return $erro('Cliente não encontrado.');
        }

        $forma = app(FormaPagamentoService::class)->resolver($empresaId, (string) $request->chave);
        if (! $forma || ! in_array($forma->codigoGravado(), FiadoService::FORMAS_RECEBIMENTO, true)) {
            return $erro('Escolha como o cliente pagou: dinheiro, PIX, cartão ou transferência.');
        }

        $caixa = app(LancamentoCaixaService::class)->caixaAbertoDoUsuario((int) session('unidade_id'), (int) auth()->id());
        if (! $caixa) {
            return $erro('Abra o caixa antes de receber: o pagamento entra no caixa.');
        }

        try {
            $rec = $this->fiado->receber(
                $empresaId,
                (int) $cli->id,
                (float) $request->valor,
                ['forma' => $forma->codigoGravado(), 'forma_chave' => $forma->chave, 'forma_nome' => $forma->nome],
                $caixa,
                (int) auth()->id(),
                $request->input('conta_ids') ?: null,
                $request->input('observacoes')
            );
        } catch (\DomainException $e) {
            return $erro($e->getMessage());
        }

        if ($json) {
            return response()->json([
                'success'     => true,
                'recebimento' => ['id' => $rec->id, 'numero' => $rec->numero, 'valor' => (float) $rec->valor, 'saldo_depois' => (float) $rec->saldo_depois],
                'recibo_html' => view('app.fiado.recibo', ['rec' => $rec->fresh(), 'autoPrint' => false])->render(),
            ]);
        }

        return redirect()->route('app.fiado.cliente', $cli->id)
            ->with('success', sprintf('Recebido R$ %s de %s. Ainda deve R$ %s.',
                number_format((float) $rec->valor, 2, ',', '.'), $cli->nome_razao_social, number_format((float) $rec->saldo_depois, 2, ',', '.')))
            ->with('fiado_recibo', $rec->id);
    }

    public function recibo(int $recebimento)
    {
        $rec = FiadoRecebimento::where('empresa_id', $this->empresaId())->findOrFail($recebimento);

        return view('app.fiado.recibo', ['rec' => $rec, 'autoPrint' => true]);
    }

    /** Nome que a empresa deu ao botão (FIADO, Crediário...). */
    private function nomeDaForma(int $empresaId): string
    {
        return FiadoService::nome($empresaId);
    }
}
