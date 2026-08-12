<?php

namespace App\Http\Middleware;

use App\Models\IntegracaoToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica a API de Integração (somente leitura) por token Bearer.
 *
 * Registrado POR CLASSE na rota (sem alias) de propósito: alias novo exige
 * mexer no bootstrap/app.php, e bootstrap/ só chega em produção com rebuild
 * da imagem (armadilha 46).
 */
class IntegracaoApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);

        $token = IntegracaoToken::autenticar($request->bearerToken());

        if (! $token) {
            Log::channel('integracao')->warning('Token inválido ou ausente', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json(['erro' => 'Não autorizado.'], 401);
        }

        // Sessão de navegador não pode vazar para a API: os controllers filtram
        // SEMPRE pela empresa do token (nunca por auth()/session()).
        $request->attributes->set('integracao_token', $token);

        $response = $next($request);

        $token->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->save();

        Log::channel('integracao')->info('Request atendida', [
            'empresa_id' => $token->empresa_id,
            'token' => $token->nome,
            'path' => $request->path(),
            'query' => $request->query(),
            'status' => $response->getStatusCode(),
            'ms' => (int) round((microtime(true) - $inicio) * 1000),
        ]);

        return $response;
    }
}
