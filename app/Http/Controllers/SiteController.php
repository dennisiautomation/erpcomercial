<?php

namespace App\Http\Controllers;

use App\Mail\NovaSolicitacaoDemonstracao;
use App\Models\SolicitacaoDemonstracao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Site público de divulgação (landing) + captura de leads de demonstração.
 */
class SiteController extends Controller
{
    /**
     * Landing pública. Usuário autenticado é levado direto ao sistema.
     */
    public function index()
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->is_admin) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->route('app.dashboard');
        }

        // no-cache no HTML: garante que o navegador sempre pegue a versão atual
        // do formulário (evita ficar preso em markup antigo após deploys).
        return response()
            ->view('site.landing')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Recebe o formulário "Agendar demonstração" (AJAX ou POST normal).
     */
    public function storeDemo(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'nome'     => ['required', 'string', 'max:120'],
            'empresa'  => ['required', 'string', 'max:160'],
            'email'    => ['required', 'email', 'max:160'],
            'whatsapp' => ['required', 'string', 'max:40'],
            'lojas'    => ['nullable', 'string', 'max:40'],
            // honeypot anti-bot: deve vir vazio
            'site'     => ['nullable', 'size:0'],
        ], [], [
            'nome'     => 'nome',
            'empresa'  => 'empresa',
            'email'    => 'e-mail',
            'whatsapp' => 'WhatsApp',
        ]);

        $lead = SolicitacaoDemonstracao::create([
            'nome'      => $data['nome'],
            'empresa'   => $data['empresa'],
            'email'     => $data['email'],
            'whatsapp'  => $data['whatsapp'],
            'qtd_lojas' => $data['lojas'] ?? null,
            'origem'    => 'landing',
            'ip'        => $request->ip(),
        ]);

        Log::info('Nova solicitação de demonstração', [
            'id'      => $lead->id,
            'empresa' => $lead->empresa,
            'email'   => $lead->email,
        ]);

        // Notifica o comercial, com o próprio cliente em cópia.
        // Falha de e-mail não pode quebrar a captura do lead.
        try {
            $destino = config('mail.from.address');
            if ($destino) {
                Mail::to($destino)
                    ->cc($lead->email)
                    ->send(new NovaSolicitacaoDemonstracao($lead));
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar e-mail de demonstração', [
                'lead_id' => $lead->id,
                'erro'    => $e->getMessage(),
            ]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Recebemos seu pedido! Em breve entramos em contato.',
            ]);
        }

        return back()->with('success', 'Recebemos seu pedido! Em breve entramos em contato.');
    }
}
