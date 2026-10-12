<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Informe seu e-mail.',
            'email.email'       => 'Formato de e-mail invalido.',
            'password.required' => 'Informe sua senha.',
        ]);

        // §343 (12/10/2026) — trava de força bruta: 8 senhas erradas em 15 min por e-mail e
        // 30 por IP → bloqueio de 15 min. Contador no cache (redis em produção: vale entre
        // workers e restarts). Login certo zera o contador do e-mail.
        $chaveEmail = 'login:email:' . Str::lower(trim((string) $request->email));
        $chaveIp = 'login:ip:' . $request->ip();
        foreach ([[$chaveEmail, 8], [$chaveIp, 30]] as [$chave, $max]) {
            if (RateLimiter::tooManyAttempts($chave, $max)) {
                $minutos = (int) ceil(RateLimiter::availableIn($chave) / 60);

                return back()
                    ->withInput($request->only('email', 'remember'))
                    ->withErrors(['email' => "Muitas tentativas de login. Aguarde {$minutos} minuto(s) e tente de novo."]);
            }
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($chaveEmail, 15 * 60);
            RateLimiter::hit($chaveIp, 15 * 60);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'E-mail ou senha incorretos.']);
        }
        RateLimiter::clear($chaveEmail);

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

        return redirect()->route('login')
            ->with('success', 'Sessao encerrada com sucesso.');
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
