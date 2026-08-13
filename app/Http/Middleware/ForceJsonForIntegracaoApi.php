<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Força Accept: application/json nas rotas api/integracao/*.
 *
 * Sem isso, um erro de validação num POST (ex.: cliente.telefone vazio no
 * /pedidos) faz o Laravel tratar a requisição como navegador: redirect 302
 * para '/' e o consumidor (agente do app.ia365, que segue redirects) recebe
 * o HTML da landing page em vez de um 422 JSON legível — foi exatamente o
 * modo de falha do teste de 13/08. Com o Accept forçado, toda exceção
 * (validação, 404, 500) volta como JSON.
 */
class ForceJsonForIntegracaoApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
