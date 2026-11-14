<?php

namespace App\Http\Middleware;

use App\Models\PosToken;
use App\Services\LancamentoCaixaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica o app da maquininha Stone (api/integracao/pos/v1) pelo token do
 * OPERADOR e monta o contexto que o PDV web espera: usuário logado e
 * session('empresa_id' | 'unidade_id' | 'caixa_id').
 *
 * A sessão é em memória (driver array), criada aqui e descartada no fim da
 * request: o POS nunca recebe cookie e uma request não enxerga a outra. Assim
 * o PdvController/CaixaController/VendaController rodam SEM alteração — a
 * regra de negócio continua num lugar só.
 *
 * Registrado POR CLASSE na rota (sem alias): alias novo exige bootstrap/app.php,
 * que só chega em produção com rebuild da imagem (armadilha 46).
 */
class PosApiToken
{
    public function handle(Request $request, Closure $next, string $exigeUnidade = 'sim'): Response
    {
        // Sessão descartável por request (o grupo de rotas tira o StartSession).
        config(['session.driver' => 'array']);
        $sessao = app('session')->driver('array');
        $sessao->start();
        $request->setLaravelSession($sessao);

        $token = PosToken::autenticar($request->bearerToken());
        if (! $token) {
            return response()->json(['erro' => 'Sessão expirada. Entre de novo.', 'codigo' => 'token_invalido'], 401);
        }

        $user = $token->user;
        if (! $user || $user->status !== 'ativo' || (int) $user->empresa_id !== (int) $token->empresa_id) {
            $token->forceFill(['revogado_em' => now()])->save();

            return response()->json(['erro' => 'Usuário inativo.', 'codigo' => 'usuario_inativo'], 401);
        }

        $empresa = $user->empresa()->withoutGlobalScopes()->first();
        if (! $empresa || $empresa->estaSuspensa()) {
            return response()->json(['erro' => 'Acesso da empresa suspenso. Fale com o responsável.', 'codigo' => 'empresa_suspensa'], 403);
        }

        Auth::guard('web')->setUser($user);
        $sessao->put('empresa_id', (int) $user->empresa_id);

        if ($token->unidade_id) {
            $sessao->put('unidade_id', (int) $token->unidade_id);

            $caixa = app(LancamentoCaixaService::class)
                ->caixaAbertoDoUsuario((int) $token->unidade_id, (int) $user->id);
            if ($caixa) {
                $sessao->put('caixa_id', (int) $caixa->id);
            }
        } elseif ($exigeUnidade === 'sim') {
            return response()->json(['erro' => 'Escolha a loja antes de continuar.', 'codigo' => 'sem_loja'], 409);
        }

        $request->attributes->set('pos_token', $token);

        $response = $next($request);

        $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();

        return $response;
    }
}
