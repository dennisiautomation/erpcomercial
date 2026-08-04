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

        if ($user && ! $user->is_admin && $user->empresa && $user->empresa->estaSuspensa()) {
            return redirect()->route('acesso-suspenso');
        }

        return $next($request);
    }
}
