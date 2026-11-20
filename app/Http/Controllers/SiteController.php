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
     *
     * O PADRÃO é o site novo (view `site.redes`) desde 16/11/2026 (ordem do Dennis).
     * A V2 de 14/08 continua em ?visual=v2 e a v1 clássica em ?visual=classico
     * (gravam na session); ?visual=novo desfaz a escolha.
     */
    public function index(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->is_admin) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->route('app.dashboard');
        }

        // 16/11/2026 (ordem do Dennis): a página inicial passa a ser o SITE NOVO — a mesma
        // página de /redes (maquininha, loja online e WhatsApp). As antigas continuam no ar:
        // ?visual=v2 (a inicial de 14/08) e ?visual=classico (a v1); ?visual=novo desfaz a escolha.
        $visual = $request->query('visual');
        if (in_array($visual, ['v2', 'classico'], true)) {
            session(['site_visual' => $visual]);
        } elseif ($visual === 'novo') {
            session()->forget('site_visual');
        }

        $view = match (session('site_visual')) {
            'classico' => 'site.landing',
            'v2' => 'site.landing-v2',
            default => 'site.redes',
        };

        // no-cache no HTML: garante que o navegador sempre pegue a versão atual
        // do formulário (evita ficar preso em markup antigo após deploys).
        return response()
            ->view($view, $view === 'site.redes' ? $this->dadosDoSite() : [])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Os planos que o site mostra: os mesmos do admin (Planos), só os ativos, na ordem de lá.
     * Preço e limites vêm do cadastro — mudou no admin, mudou no site. Se a tabela não
     * responder, o site abre sem a seção em vez de cair.
     */
    /**
     * `contratacao` = chave "Contratação pelo site" (Admin → Planos). Desligada, a página
     * não mostra preço nenhum: planos saem como "Sob consulta" e os botões levam à conversa.
     */
    private function dadosDoSite(): array
    {
        return [
            'planos'      => $this->planosDoSite(),
            'contratacao' => \App\Services\Plataforma\ContratacaoSite::ativa(),
        ];
    }

    private function planosDoSite()
    {
        try {
            return \App\Models\Plano::ativo()->orderBy('ordem')->orderBy('preco_mensal')->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Página de venda para redes de lojas (2 a 10 lojas). É a página de destino
     * dos anúncios e do material de indicação; não redireciona quem está logado,
     * para o parceiro conseguir abrir e mostrar.
     */
    public function redes()
    {
        return response()
            ->view('site.redes', $this->dadosDoSite())
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
            // de qual página veio o pedido (landing geral ou a de redes de lojas)
            'origem'   => ['nullable', 'in:landing,redes'],
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
            'origem'    => $data['origem'] ?? 'landing',
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
