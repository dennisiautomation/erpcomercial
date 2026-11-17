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
        $modeloLoja = $ctx->rascunho ? $loja->rascunhoEfetivo()['modelo'] : $loja->modelo;

        View::share([
            'ctx' => $ctx,
            'loja' => $loja,
            'tema' => $tema,
            'temaEstilo' => TemaCss::estilo($tema),
            'temaFontes' => TemaCss::fontesUrl($tema),
            'temaAtributos' => TemaCss::atributos($tema),
            // 17/11/2026: modelo em uso — o modelo exclusivo carrega o visual próprio (loja/modelos/{chave})
            'modeloLoja' => \App\Services\LojaOnline\ModelosLoja::ehExclusivo($modeloLoja) ? $modeloLoja : null,
            // 15/11/2026: conta do comprador logada nesta loja (ou null)
            'contaLoja' => $loja->opcao('conta_cliente') ? \App\Models\LojaConta::atual($loja) : null,
        ]);

        // Visita do dia (uma por sessão): só gente de fora, em página — não conta a equipe nem robô.
        if ($request->isMethod('GET') && ! $daCasa && ! $request->expectsJson() && \App\Services\LojaOnline\MetricasLoja::humano($request)) {
            \App\Services\LojaOnline\MetricasLoja::contar($loja, 'visitas');
            \App\Services\LojaOnline\MetricasLoja::sessao($loja, $request);
        }

        $request->route()->forgetParameter('loja');

        return $next($request);
    }
}
