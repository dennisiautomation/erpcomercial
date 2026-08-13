<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\IntegracaoToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tokens da API de Integração (Gersen) — versão da área do cliente
 * (/app/configuracoes/integracao, menu Gestão, dono/admin via
 * permission:configuracoes). O admin da plataforma tem a mesma gestão
 * na aba Integração de /admin/empresas/{id}.
 */
class IntegracaoTokenController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        // Admin da plataforma tem empresa_id NULL (armadilha 25) — ele gerencia
        // tokens pela tela de empresas do /admin, onde escolhe QUAL empresa.
        if (! $empresaId) {
            return redirect()->route('admin.empresas.index')
                ->with('info', 'Escolha a empresa e use a aba Integração.');
        }

        $tokens = IntegracaoToken::where('empresa_id', $empresaId)
            ->orderByDesc('created_at')
            ->get();

        return view('app.integracao.index', compact('tokens'));
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = $request->user()->empresa_id;
        abort_unless($empresaId, 403);

        $validated = $request->validate([
            'nome' => ['nullable', 'string', 'max:80'],
        ]);

        [$token, $claro] = IntegracaoToken::gerar(
            $empresaId,
            $validated['nome'] ?: 'Gersen',
            $request->user()->id,
        );

        return redirect()
            ->route('app.integracao.index')
            ->with('success', "Token \"{$token->nome}\" gerado. Copie agora — ele não será exibido de novo.")
            ->with('novo_token', $claro);
    }

    public function revogar(Request $request, IntegracaoToken $token): RedirectResponse
    {
        abort_unless($request->user()->empresa_id && $token->empresa_id === $request->user()->empresa_id, 404);

        $token->update(['ativo' => false]);

        return redirect()
            ->route('app.integracao.index')
            ->with('success', "Token \"{$token->nome}\" revogado.");
    }
}
