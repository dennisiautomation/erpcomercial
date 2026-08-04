<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\RedefinirSenha;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /** Validade do token em minutos. */
    private const TOKEN_EXPIRA_MIN = 60;

    /**
     * Show "forgot password" form.
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Gera o token e envia o link por e-mail (fila).
     *
     * A resposta é a MESMA existindo ou não a conta — nunca revelar se um
     * e-mail está cadastrado (antes o link de troca aparecia na tela para
     * qualquer pessoa que digitasse o e-mail: tomada de conta trivial).
     */
    public function sendReset(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::withoutGlobalScopes()
            ->where('email', $request->email)
            ->where('status', 'ativo')
            ->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $link = route('password.reset', $token) . '?email=' . urlencode($user->email);

            Mail::to($user->email)->queue(new RedefinirSenha($user, $link));
        }

        return back()->with('success',
            'Se este e-mail estiver cadastrado, você receberá as instruções de redefinição em instantes. Confira também a caixa de spam.');
    }

    /**
     * Show reset form.
     */
    public function showResetForm(Request $request, $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Reset password.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'Token invalido ou expirado.']);
        }

        if (\Illuminate\Support\Carbon::parse($record->created_at)->addMinutes(self::TOKEN_EXPIRA_MIN)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return back()->withErrors(['email' => 'Este link expirou. Solicite uma nova redefinição.']);
        }

        User::withoutGlobalScopes()
            ->where('email', $request->email)
            ->update(['password' => Hash::make($request->password)]);
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('success', 'Senha redefinida com sucesso!');
    }
}
