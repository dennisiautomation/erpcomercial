<?php

namespace App\Services\Ajuda;

use App\Models\AjudaConversa;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Abre o chamado no app.ia365 pelo mesmo endpoint do agente de suporte do
 * WhatsApp (POST /api/public/tickets/agente, §294 do app.ia365): Bearer
 * SUPORTE_AGENTE_TOKEN, JSON, limite 3/h por telefone e sem duplicar o mesmo
 * assunto aberto. O chamado cai na lista de chamados do cliente IA365 de lá.
 */
class ChamadoIa365
{
    public function abrir(User $user, AjudaConversa $conversa, string $assunto, string $descricao, string $prioridade, ?string $pagina): array
    {
        $url = (string) config('ajuda.chamado.url');
        $token = (string) config('ajuda.chamado.token');
        if ($url === '' || $token === '') {
            return ['ok' => false, 'mensagem' => 'A abertura de chamado não está configurada. Peça para a pessoa falar com o suporte da IA365 pelo WhatsApp.'];
        }

        $max = (int) config('ajuda.chamado.max_por_dia_usuario', 5);
        $hoje = AjudaConversa::query()->where('user_id', $user->id)->where('chamado_aberto_em', '>=', now()->subDay())->count();
        if ($hoje >= $max) {
            return ['ok' => false, 'mensagem' => "Este usuário já abriu {$max} chamados nas últimas 24 horas. Diga que a equipe vai atender os que já estão abertos."];
        }

        $empresa = $user->empresa;
        $loja = session('unidade_id') ? \App\Models\Unidade::withoutGlobalScopes()->where('id', session('unidade_id'))->value('nome') : null;
        $nomeEmpresa = $empresa ? trim(($empresa->nome_fantasia ?: $empresa->razao_social) . ' — CNPJ ' . $empresa->cnpj) : 'sem empresa (usuário da plataforma)';

        $contexto = collect([
            'Aberto pelo agente de ajuda dentro do ERP Comercial (erp.ia365.com.br).',
            'Empresa: ' . $nomeEmpresa . ($empresa ? ' (id ' . $empresa->id . ')' : ''),
            'Loja: ' . ($loja ?: '—'),
            'Usuário: ' . $user->name . ' <' . $user->email . '>, perfil ' . ($user->perfil?->value ?? $user->perfil),
            'Tela: ' . ($pagina ?: '—'),
        ])->implode("\n");

        $conversaTxt = $conversa->mensagens()->whereIn('papel', ['user', 'assistant'])->whereNotNull('conteudo')
            ->orderBy('id')->get()->take(-20)
            ->map(fn ($m) => ($m->papel === 'user' ? 'Cliente: ' : 'Agente: ') . Str::limit(trim((string) $m->conteudo), 600))
            ->implode("\n");

        $corpo = [
            'nome' => $user->name,
            'empresa' => $nomeEmpresa,
            'telefone' => preg_replace('/\D/', '', (string) ($user->telefone ?: $empresa?->telefone)),
            'email' => $user->email,
            'assunto' => Str::limit(trim($assunto), 100, ''),
            'descricao' => trim($descricao) . "\n\n---\n" . $contexto . "\n\n--- Conversa ---\n" . $conversaTxt,
            'prioridade' => in_array($prioridade, ['low', 'medium', 'high', 'urgent'], true) ? $prioridade : 'medium',
            'categoria' => 'erp',
        ];

        try {
            $r = Http::withToken($token)->acceptJson()->timeout(20)->post($url, $corpo);
        } catch (\Throwable $e) {
            Log::warning('[Ajuda] chamado: app.ia365 fora do ar', ['erro' => $e->getMessage()]);

            return ['ok' => false, 'mensagem' => 'O sistema de chamados não respondeu agora. Peça para tentar de novo em alguns minutos ou falar com o suporte pelo WhatsApp.'];
        }

        $j = (array) $r->json();
        if ($r->successful() && ($j['ok'] ?? false)) {
            $protocolo = (string) ($j['protocolo'] ?? '');
            $conversa->forceFill(['chamado_protocolo' => $protocolo, 'chamado_aberto_em' => now()])->save();
            Log::info('[Ajuda] chamado aberto', ['empresa' => $empresa?->id, 'user' => $user->id, 'protocolo' => $protocolo, 'duplicado' => $j['duplicado'] ?? false]);

            return [
                'ok' => true,
                'protocolo' => $protocolo,
                'duplicado' => (bool) ($j['duplicado'] ?? false),
                'mensagem' => ($j['duplicado'] ?? false)
                    ? 'Já existia um chamado aberto para este mesmo assunto; informe este protocolo.'
                    : 'Chamado aberto. Informe o protocolo e diga que a equipe da IA365 vai responder.',
            ];
        }

        Log::warning('[Ajuda] chamado recusado', ['status' => $r->status(), 'resposta' => Str::limit($r->body(), 500)]);

        return ['ok' => false, 'mensagem' => (string) ($j['mensagem'] ?? 'Não foi possível abrir o chamado agora.')];
    }
}
