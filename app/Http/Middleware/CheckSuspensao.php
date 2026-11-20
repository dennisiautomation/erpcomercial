<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueio TOTAL por pendência financeira da plataforma (cobrança direta).
 *
 * Empresa com `cobranca_suspensa_em` preenchido: todos os usuários dela caem
 * na tela "acesso suspenso" — PDV, vendas, tudo. Reativa na hora em que o
 * admin da IA365 marca a fatura como paga. Decisão do Dennis (04/08/2026).
 */
class CheckSuspensao
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin da plataforma em "acesso como" entra mesmo com a empresa
        // suspensa — é justamente quando ele precisa inspecionar (o banner
        // do layout sinaliza a suspensão).
        // Empresa que veio do site (20/11/2026) segue abrindo as faturas mesmo suspensa: é lá que
        // ela paga por cartão ou gera o boleto. As demais não mudam.
        if ($user && ! $user->is_admin && $user->empresa && $user->empresa->estaSuspensa()
            && ! session()->has('acesso_como_admin_id')
            && ! ($user->empresa->veioDoSite() && $request->routeIs('app.faturas.*', 'logout'))) {
            return redirect()->route('acesso-suspenso');
        }

        // Autocontratação (20/11/2026): empresa que se cadastrou SOZINHA pelo site e cujo teste
        // grátis venceu (sem assinatura nem cobrança ativa) fica com o sistema inteiro parado na
        // tela de plano. Empresas criadas pelo admin (todas as anteriores) não entram nesta regra.
        if ($user && ! $user->is_admin && $user->empresa
            && $user->empresa->testeDoSiteVencido()
            && ! session()->has('acesso_como_admin_id')
            && ! $request->routeIs('app.plano-expirado', 'app.faturas.*', 'app.assinatura.*', 'logout')) {
            return redirect()->route('app.plano-expirado');
        }

        return $next($request);
    }
}
