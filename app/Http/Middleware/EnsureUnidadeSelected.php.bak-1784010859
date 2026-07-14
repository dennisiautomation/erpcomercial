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
