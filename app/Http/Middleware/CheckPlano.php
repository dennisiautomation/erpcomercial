<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlano
{
    public function handle(Request $request, Closure $next, string $feature = ''): Response
    {
        $user = $request->user();

        if (! $user || $user->is_admin) {
            return $next($request);
        }

        $empresa = $user->empresa;

        if (! $empresa) {
            abort(403);
        }

        // Check if subscription is active (trial or paid)
        if (! $empresa->isAssinaturaAtiva()) {
            return redirect()->route('app.plano-expirado');
        }

        // Check feature access
        if ($feature) {
            $plano = $empresa->getPlanoAtivo();

            if ($plano && ! $plano->isFeatureEnabled($feature)) {
                abort(403, 'Seu plano nao inclui esta funcionalidade. Faca upgrade para acessar.');
            }
        }

        return $next($request);
    }
}
