<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Notificacao;
use App\Services\NotificacaoService;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    /**
     * Lista todas as notificações do usuário.
     */
    public function index(Request $request)
    {
        $notificacoes = Notificacao::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('app.notificacoes.index', compact('notificacoes'));
    }

    /**
     * Marca uma notificação como lida.
     */
    public function marcarLida(Notificacao $notificacao)
    {
        abort_if($notificacao->user_id !== auth()->id(), 403);

        $notificacao->update([
            'lida' => true,
            'lida_em' => now(),
        ]);

        if ($notificacao->url) {
            return redirect($notificacao->url);
        }

        return back()->with('success', 'Notificação marcada como lida.');
    }

    /**
     * Marca todas as notificações como lidas.
     */
    public function marcarTodasLidas()
    {
        Notificacao::where('user_id', auth()->id())
            ->where('lida', false)
            ->update([
                'lida' => true,
                'lida_em' => now(),
            ]);

        return back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }

    /**
     * Retorna JSON com contagem de não lidas e últimas notificações (para AJAX do sino).
     */
    public function contar()
    {
        $userId = auth()->id();

        $count = NotificacaoService::contarNaoLidas($userId);

        $recentes = Notificacao::where('user_id', $userId)
            ->naoLidas()
            ->recentes(5)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'tipo' => $n->tipo,
                    'titulo' => $n->titulo,
                    'mensagem' => $n->mensagem,
                    'url' => $n->url ? route('app.notificacoes.lida', $n->id) : null,
                    'icone' => $n->icone,
                    'cor' => $n->cor,
                    'tempo' => $n->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'count' => $count,
            'notificacoes' => $recentes,
        ]);
    }
}
