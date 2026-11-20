<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\PlataformaFatura;
use App\Services\Plataforma\AssinaturaSite;
use App\Services\Plataforma\FaturasPlataforma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Assinar agora" (20/11/2026): a empresa que veio do site escolhe PIX, boleto ou cartão.
 * A cobrança nasce no caixa.ia365; aqui ficam a escolha, o link de cartão e a volta dele.
 * Só dono, gerente, admin e financeiro — os mesmos da tela "Faturas".
 */
class AssinaturaController extends Controller
{
    private function exigir(Request $request): void
    {
        abort_unless(FaturasPlataforma::podeVer($request->user()), 403, 'A assinatura do sistema fica com o dono, o gerente e o financeiro da loja.');
        abort_unless(AssinaturaSite::vale($request->user()->empresa), 404);
    }

    private function fatura(Request $request, int $id): PlataformaFatura
    {
        return PlataformaFatura::where('empresa_id', $request->user()->empresa_id)->findOrFail($id);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->exigir($request);
        $empresa = $request->user()->empresa;

        if (! AssinaturaSite::podeAssinar($empresa)) {
            return redirect()->route('app.faturas.index');
        }

        return view('app.assinatura.index', [
            'empresa' => $empresa,
            'plano' => $empresa->getPlanoAtivo(),
            'valor' => AssinaturaSite::valorMensal($empresa),
            'vencimento' => AssinaturaSite::primeiroVencimento($empresa),
            'emTeste' => $empresa->isTrialActive(),
            'cartao' => AssinaturaSite::cartaoDisponivel(),
            'ligada' => AssinaturaSite::caixaConfigurado(),
            'tolerancia' => AssinaturaSite::toleranciaDias(),
        ]);
    }

    public function assinar(Request $request): RedirectResponse
    {
        $this->exigir($request);
        $d = $request->validate(['forma' => ['required', 'in:pix,boleto,cartao']], ['forma.*' => 'Escolha PIX, boleto ou cartão.']);
        $empresa = $request->user()->empresa;

        if (! AssinaturaSite::podeAssinar($empresa)) {
            return redirect()->route('app.faturas.index');
        }

        $r = AssinaturaSite::assinar($empresa, $d['forma'], $request->user());
        if (! $r['ok']) {
            return back()->withErrors(['forma' => $r['erro']]);
        }

        if ($d['forma'] === 'cartao') {
            $l = AssinaturaSite::linkCartao($r['fatura']);
            if ($l['ok']) {
                return redirect()->away($l['link']);
            }

            return redirect()->route('app.faturas.index')->with('warning', $l['erro'] . ' A fatura já está aqui com o PIX.');
        }

        return redirect()->route('app.faturas.index')->with('success', $d['forma'] === 'boleto'
            ? 'Assinatura pedida. O boleto da primeira mensalidade está abaixo.'
            : 'Assinatura pedida. O PIX da primeira mensalidade está abaixo.');
    }

    /** "Pagar com cartão" numa fatura em aberto: abre o link da InfinitePay. */
    public function cartao(Request $request, int $fatura): RedirectResponse
    {
        $this->exigir($request);
        $l = AssinaturaSite::linkCartao($this->fatura($request, $fatura));

        return $l['ok'] ? redirect()->away($l['link']) : back()->with('error', $l['erro']);
    }

    /** "Gerar boleto" / "Gerar PIX" numa fatura em aberto. */
    public function emitir(Request $request, int $fatura): RedirectResponse
    {
        $this->exigir($request);
        $d = $request->validate(['tipo' => ['required', 'in:pix,boleto']]);
        $r = AssinaturaSite::emitir($this->fatura($request, $fatura), $d['tipo']);

        return $r['ok']
            ? back()->with('success', $d['tipo'] === 'boleto' ? 'Boleto gerado. A linha para copiar está na fatura.' : 'PIX gerado. O código para copiar está na fatura.')
            : back()->with('error', $r['erro']);
    }

    /** A InfinitePay devolve a pessoa para cá depois do pagamento. Só vale o que payment_check disser. */
    public function retornoCartao(Request $request): RedirectResponse
    {
        $nsu = (string) $request->query('order_nsu', '');
        $fatura = $nsu !== '' ? PlataformaFatura::where('empresa_id', $request->user()->empresa_id)->where('cartao_nsu', $nsu)->first() : null;
        if (! $fatura) {
            return redirect()->route('app.faturas.index');
        }

        $r = AssinaturaSite::conferirCartao($fatura, $request->query('transaction_nsu'), $request->query('slug'));

        return redirect()->route('app.faturas.index')->with($r['pago'] ? 'success' : 'warning', $r['pago']
            ? 'Pagamento no cartão confirmado. Obrigado!'
            : 'Ainda não recebemos a confirmação do cartão. Assim que a InfinitePay confirmar, a fatura é baixada sozinha.');
    }
}
