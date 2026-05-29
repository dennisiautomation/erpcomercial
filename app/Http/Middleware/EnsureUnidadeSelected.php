<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUnidadeSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Garante empresa_id na sessão para os controllers multi-tenant.
        // É lido em ~70 pontos (PDV, Vendas, Pedidos, Fiscal, Multiloja...) mas
        // o login só gravava unidade_id — sem isto, session('empresa_id') fica
        // NULL numa sessão recém-logada e as buscas/listagens voltam vazias.
        if ($user->empresa_id && session('empresa_id') !== $user->empresa_id) {
            session(['empresa_id' => $user->empresa_id]);
        }

        // Admin não precisa de unidade selecionada
        if ($user->is_admin) {
            return $next($request);
        }

        // Se não há unidade na sessão, redirecionar para seleção
        if (! session()->has('unidade_id')) {
            return redirect()->route('selecionar-unidade');
        }

        return $next($request);
    }
}
