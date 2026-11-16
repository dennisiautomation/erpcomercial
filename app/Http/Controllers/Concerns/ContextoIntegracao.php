<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Quem está mexendo nas integrações de pagamento e entrega (16/11/2026).
 *
 * A gravação mora num lugar só: os controllers de Admin (PIX Sicredi, Asaas,
 * InfinitePay, Melhor Envio, Uber Direct, cartão em uso). Eles atendem duas
 * portas: o admin da plataforma, pela aba Integração da empresa, e o lojista,
 * pela tela "Pagamento e entrega" (App\PagamentoEntregaController), que chama
 * os mesmos métodos marcando o pedido com `integracao_lojista` = id da
 * empresa DELE. A marca só é posta pelo servidor (atributo do Request, não
 * vem do formulário), então o lojista nunca alcança outra empresa.
 */
trait ContextoIntegracao
{
    protected function doLojista(?Request $request = null): bool
    {
        return (bool) ($request ?? request())->attributes->get('integracao_lojista');
    }

    protected function autorizarIntegracao(Request $request, Empresa $empresa): void
    {
        if ($this->doLojista($request)) {
            abort_unless((int) $request->attributes->get('integracao_lojista') === (int) $empresa->id, 403);

            return;
        }

        abort_unless((bool) $request->user()?->is_admin, 403);
    }

    /** Para onde volta depois de salvar/testar: a tela de quem pediu. */
    protected function destino(Empresa $empresa): RedirectResponse
    {
        return $this->doLojista()
            ? redirect()->route('app.pagamento-entrega.index')
            : redirect()->route('admin.empresas.show', $empresa);
    }
}
