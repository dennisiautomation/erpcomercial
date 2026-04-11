<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Exibir formulario de login.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Processar tentativa de login.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Credenciais invalidas.']);
        }

        $request->session()->regenerate();

        return $this->redirectAfterLogin(Auth::user());
    }

    /**
     * Encerrar sessao.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Redirecionar apos login conforme perfil.
     */
    private function redirectAfterLogin($user): RedirectResponse
    {
        if ($user->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        // Se ja tem unidade na sessao, vai direto pro app
        if (session()->has('unidade_id')) {
            return redirect()->route('app.dashboard');
        }

        // Se o usuario so tem 1 unidade, seleciona automaticamente
        $unidades = $user->unidades;

        if ($unidades->count() === 1) {
            session(['unidade_id' => $unidades->first()->id]);
            return redirect()->route('app.dashboard');
        }

        return redirect()->route('selecionar-unidade');
    }
}
