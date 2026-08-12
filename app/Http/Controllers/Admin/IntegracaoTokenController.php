<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\IntegracaoToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tokens da API de Integração (Gersen) — administrados pela plataforma,
 * na aba Integração da empresa (/admin/empresas/{id}).
 */
class IntegracaoTokenController extends Controller
{
    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'nome' => ['nullable', 'string', 'max:80'],
        ]);

        [$token, $claro] = IntegracaoToken::gerar(
            $empresa->id,
            $validated['nome'] ?: 'Gersen',
            $request->user()->id,
        );

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', "Token \"{$token->nome}\" gerado. Copie agora — ele não será exibido de novo.")
            ->with('novo_token', $claro)
            ->with('abrir_integracao', true);
    }

    public function revogar(Request $request, Empresa $empresa, IntegracaoToken $token): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        abort_unless($token->empresa_id === $empresa->id, 404);

        $token->update(['ativo' => false]);

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', "Token \"{$token->nome}\" revogado.")
            ->with('abrir_integracao', true);
    }
}
