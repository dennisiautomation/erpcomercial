<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\ConfiguracaoLoja;
use App\Models\PosEvento;
use App\Models\PosToken;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Pos\PosPermissoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Login do operador no app da maquininha (28/10/2026).
 *
 * Mesma trava de força bruta do login web (§343): 8 erros por e-mail e 30 por
 * IP em 15 min. O token sai amarrado a usuário + loja + aparelho (serial do POS).
 */
class PosAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
            'serial' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'app_versao' => ['nullable', 'string', 'max:20'],
        ]);

        $chaveEmail = 'pos-login:email:' . Str::lower(trim($dados['email']));
        $chaveIp = 'pos-login:ip:' . $request->ip();
        foreach ([[$chaveEmail, 8], [$chaveIp, 30]] as [$chave, $max]) {
            if (RateLimiter::tooManyAttempts($chave, $max)) {
                $minutos = (int) ceil(RateLimiter::availableIn($chave) / 60);

                return response()->json(['erro' => "Muitas tentativas seguidas. Aguarde {$minutos} minuto(s) e tente de novo."], 429);
            }
        }

        $user = User::withoutGlobalScopes()->where('email', $dados['email'])->first();

        if (! $user || ! Hash::check($dados['senha'], $user->password)) {
            RateLimiter::hit($chaveEmail, 15 * 60);
            RateLimiter::hit($chaveIp, 15 * 60);

            return response()->json(['erro' => 'E-mail ou senha não conferem. Confira e tente de novo.'], 422);
        }
        RateLimiter::clear($chaveEmail);

        if ($user->status !== 'ativo') {
            return response()->json(['erro' => 'Seu usuário está desativado. Peça ao gerente da loja para reativar.'], 403);
        }
        if (! $user->empresa_id) {
            // Admin da plataforma não vende no POS de nenhuma empresa.
            return response()->json(['erro' => 'Seu usuário não está ligado a nenhuma empresa. Fale com o suporte.'], 403);
        }
        if (! PosPermissoes::podeUsarApp($user)) {
            return response()->json(['erro' => 'Seu perfil de acesso não usa o app de vendas. Peça ao gerente para liberar (vendedor, caixa ou gerente).'], 403);
        }

        $empresa = $user->empresa()->withoutGlobalScopes()->first();
        if (! $empresa || $empresa->estaSuspensa()) {
            return response()->json(['erro' => 'O acesso da empresa está suspenso. Fale com o responsável pela conta.'], 403);
        }

        $lojas = $this->lojasDoUsuario($user);
        if ($lojas->isEmpty()) {
            return response()->json(['erro' => 'Seu usuário não está em nenhuma loja ativa. Peça ao gerente para incluir você em uma loja.'], 403);
        }

        $unidadeId = $lojas->count() === 1 ? (int) $lojas->first()->id : null;

        [$token, $claro] = PosToken::gerar($user, $unidadeId, $dados);

        $this->evento($token, 'login', 'Login no app POS');

        return response()->json([
            'token' => $claro,
            'expira_em' => $token->expira_em->toIso8601String(),
            'precisa_escolher_loja' => $unidadeId === null,
            'lojas' => $lojas->map(fn ($u) => ['id' => $u->id, 'nome' => $u->nome, 'cidade' => $u->cidade, 'uf' => $u->uf])->values(),
        ]);
    }

    /** Troca a loja do token (operador com mais de uma loja). */
    public function escolherLoja(Request $request): JsonResponse
    {
        $request->validate(['unidade_id' => ['required', 'integer']]);
        /** @var PosToken $token */
        $token = $request->attributes->get('pos_token');
        $user = $request->user();

        $loja = $this->lojasDoUsuario($user)->firstWhere('id', (int) $request->unidade_id);
        if (! $loja) {
            return response()->json(['erro' => 'Você não tem acesso a esta loja.'], 403);
        }

        $token->forceFill(['unidade_id' => $loja->id])->save();
        $this->evento($token, 'loja_escolhida', "Loja {$loja->nome}");

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var PosToken $token */
        $token = $request->attributes->get('pos_token');
        $token->forceFill(['revogado_em' => now()])->save();
        $this->evento($token, 'logout', 'Saiu do app POS');

        return response()->json(['ok' => true]);
    }

    /**
     * Tudo o que o app precisa para desenhar as telas: quem é, onde está, o
     * caixa, o que o perfil pode fazer e as regras de preço/parcelas da loja.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $empresaId = (int) session('empresa_id');
        $unidadeId = (int) session('unidade_id');

        $unidade = Unidade::withoutGlobalScopes()->find($unidadeId);
        $empresa = $user->empresa()->withoutGlobalScopes()->first();
        $configLoja = ConfiguracaoLoja::daUnidade($empresaId, $unidadeId);
        $fiscal = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)->first();

        $caixa = session('caixa_id') ? \App\Models\Caixa::withoutGlobalScopes()->find(session('caixa_id')) : null;

        return response()->json([
            'usuario' => [
                'id' => $user->id,
                'nome' => $user->name,
                'email' => $user->email,
                'perfil' => $user->perfil->value,
                'perfil_nome' => $user->perfil->label(),
            ],
            'empresa' => [
                'id' => $empresa?->id,
                'nome' => $empresa?->nome_fantasia ?: $empresa?->razao_social,
                'cnpj' => $empresa?->cnpj,
            ],
            'loja' => $unidade ? [
                'id' => $unidade->id,
                'nome' => $unidade->nome,
                'cidade' => $unidade->cidade,
                'uf' => $unidade->uf,
            ] : null,
            'lojas' => $this->lojasDoUsuario($user)->map(fn ($u) => ['id' => $u->id, 'nome' => $u->nome])->values(),
            'caixa' => $caixa ? [
                'id' => $caixa->id,
                'numero' => $caixa->numero_caixa,
                'aberto_em' => $caixa->aberto_em?->toIso8601String(),
                'valor_abertura' => (float) $caixa->valor_abertura,
            ] : null,
            'permissoes' => PosPermissoes::doUsuario($user),
            'regras' => [
                'max_parcelas' => (int) ($configLoja->max_parcelas ?: 12),
                'juros_por_parcela' => (object) ($configLoja->juros_por_parcela ?? []), // {"6": 8, "12": 16} = % de juros por nº de parcelas
                'emite_nfce' => (bool) ($fiscal?->emissao_fiscal_ativa && $fiscal?->emite_nfce),
                'cpf_emite_fiscal' => (bool) $configLoja->cpf_emite_fiscal,
                'cupom_automatico_cartao' => (bool) $configLoja->cupom_automatico_cartao,
            ],
        ]);
    }

    private function lojasDoUsuario(User $user)
    {
        $base = Unidade::withoutGlobalScopes()
            ->where('empresa_id', $user->empresa_id)
            ->where('status', 'ativa')
            ->orderBy('nome');

        if ($user->isDono() || $user->isAdmin()) {
            return $base->get();
        }

        return $base->whereIn('id', $user->unidades()->pluck('unidades.id'))->get();
    }

    private function evento(PosToken $token, string $tipo, string $mensagem): void
    {
        PosEvento::create([
            'empresa_id' => $token->empresa_id,
            'user_id' => $token->user_id,
            'unidade_id' => $token->unidade_id,
            'dispositivo_serial' => $token->dispositivo_serial,
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'dados' => ['ip' => request()->ip(), 'modelo' => $token->dispositivo_modelo, 'app_versao' => $token->app_versao],
            'ocorrido_em' => now(),
        ]);
    }
}
