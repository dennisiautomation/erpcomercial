<?php

namespace App\Http\Middleware;

use App\Models\LojaOnline;
use App\Services\LojaOnline\LojaContexto;
use App\Services\LojaOnline\TemaCss;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta de entrada das rotas /loja/{loja}/... (14/11/2026): acha a loja pelo
 * slug, decide se o visitante pode vê-la e monta o LojaContexto do pedido.
 *
 * - Loja inexistente ou não liberada pela plataforma → 404.
 * - Liberada mas fora do ar → só quem está logado no ERP da MESMA empresa (ou o
 *   admin da plataforma) enxerga, como prévia; o público vê "em preparação".
 * - `?rascunho=1` (o editor visual) também só para essas pessoas.
 */
class LojaOnlineMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('loja');
        $loja = LojaOnline::where('slug', $slug)->where('ativo', true)->first();
        abort_unless($loja, 404);

        $user = $request->user();
        $daCasa = $user && ($user->is_admin || (int) $user->empresa_id === (int) $loja->empresa_id);

        $ctx = app(LojaContexto::class);
        $ctx->loja = $loja;
        $ctx->porHost = (bool) $request->attributes->get('loja_por_host', false);
        $ctx->previa = ! $loja->noAr() && $daCasa;
        $ctx->rascunho = $daCasa && $request->boolean('rascunho');

        if (! $loja->noAr() && ! $daCasa) {
            return response()->view('loja.fechada', ['loja' => $loja], 503);
        }

        $tema = $ctx->rascunho ? $loja->rascunhoEfetivo()['tema'] : $loja->temaEfetivo();

        View::share([
            'ctx' => $ctx,
            'loja' => $loja,
            'tema' => $tema,
            'temaEstilo' => TemaCss::estilo($tema),
            'temaFontes' => TemaCss::fontesUrl($tema),
            'temaAtributos' => TemaCss::atributos($tema),
        ]);

        $request->route()->forgetParameter('loja');

        return $next($request);
    }
}
