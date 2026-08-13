<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Acessar como": admin da plataforma entra no sistema logado como um
 * usuário da empresa-cliente (o dono, de preferência) — vê exatamente o
 * que o cliente vê. A sessão guarda quem é o admin real
 * (acesso_como_admin_id) para o banner, a auditoria e a volta.
 */
class AcessoComoController extends Controller
{
    public function entrar(Request $request, Empresa $empresa): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin->is_admin, 403);

        // Não encadear acessos: primeiro voltar ao admin
        if (session()->has('acesso_como_admin_id')) {
            return redirect()->route('admin.empresas.index')
                ->with('error', 'Você já está acessando como outro usuário — volte ao admin antes.');
        }

        // Alvo: usuário ativo da empresa com o maior perfil (dono primeiro).
        // Nunca outro admin da plataforma.
        $alvo = User::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->where('status', 'ativo')
            ->where('is_admin', false)
            ->get()
            ->sortByDesc(fn (User $u) => ($u->isDono() ? 1 : 0) * 1000 + ($u->perfil?->nivel() ?? 0))
            ->first();

        if (! $alvo) {
            return back()->with('error',
                "A empresa {$empresa->razao_social} não tem nenhum usuário ativo para acessar.");
        }

        activity('acesso_como')
            ->causedBy($admin)
            ->withProperties([
                'empresa_id' => $empresa->id,
                'empresa' => $empresa->razao_social,
                'usuario_alvo_id' => $alvo->id,
                'usuario_alvo_email' => $alvo->email,
            ])
            ->log('acesso_como_iniciado');

        $adminId = $admin->id;

        Auth::login($alvo);
        $request->session()->regenerate();
        // Sessão limpa de contexto anterior; o fluxo normal (EnsureUnidadeSelected
        // → selecionar-unidade) escolhe a loja como faria com o próprio cliente.
        session()->forget(['empresa_id', 'unidade_id']);
        session(['acesso_como_admin_id' => $adminId]);

        return redirect()->route('app.dashboard')
            ->with('success', "Você está acessando como {$alvo->name} — {$empresa->razao_social}.");
    }

    public function sair(Request $request): RedirectResponse
    {
        $adminId = session('acesso_como_admin_id');

        abort_unless($adminId, 403);

        $admin = User::withoutGlobalScopes()->find($adminId);

        // Admin sumiu/rebaixado no meio do caminho: encerra tudo por segurança
        if (! $admin || ! $admin->is_admin) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        $impersonado = $request->user();

        activity('acesso_como')
            ->causedBy($admin)
            ->withProperties([
                'empresa_id' => $impersonado?->empresa_id,
                'usuario_alvo_id' => $impersonado?->id,
                'usuario_alvo_email' => $impersonado?->email,
            ])
            ->log('acesso_como_encerrado');

        Auth::login($admin);
        $request->session()->regenerate();
        session()->forget(['acesso_como_admin_id', 'empresa_id', 'unidade_id']);

        return redirect()->route('admin.empresas.index')
            ->with('success', 'Você voltou ao seu usuário administrador.');
    }
}
