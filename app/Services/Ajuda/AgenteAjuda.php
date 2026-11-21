<?php

namespace App\Services\Ajuda;

use App\Models\AjudaConversa;
use App\Models\AjudaMensagem;
use App\Models\User;
use App\Services\FiadoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * O agente do botão "Ajuda": recebe a pergunta, conversa com a IA (DeepSeek por
 * padrão, qualquer API no formato OpenAI), deixa a IA chamar as ferramentas de
 * FerramentasAjuda (só leitura, só a empresa do usuário) e, se for o caso, abre
 * o chamado no app.ia365. Grava tudo em ajuda_mensagens.
 */
class AgenteAjuda
{
    public function __construct(
        private BaseConhecimento $base,
        private ChamadoIa365 $chamado,
    ) {}

    /** @return array{resposta:string, protocolo:?string} */
    public function responder(User $user, AjudaConversa $conversa, string $texto, ?string $pagina): array
    {
        $texto = Str::limit(trim($texto), 2000, '');
        $this->gravar($conversa, 'user', $texto, null, null, $pagina);
        if (! $conversa->titulo) {
            $conversa->forceFill(['titulo' => Str::limit($texto, 150)])->save();
        }

        $ferr = new FerramentasAjuda($user, $this->base);
        $mensagens = array_merge(
            [['role' => 'system', 'content' => $this->instrucoes($user, $pagina)]],
            $this->historico($conversa)
        );

        $protocolo = null;
        $rodadas = (int) config('ajuda.max_rodadas_ferramenta', 6);
        for ($i = 0; $i <= $rodadas; $i++) {
            $semFerramenta = $i === $rodadas; // última volta: obriga a responder em texto
            $r = $this->chamarIa($mensagens, $semFerramenta ? [] : $ferr->definicoes());
            if (! $r['ok']) {
                $falha = 'Desculpe, não consegui pensar na resposta agora (o serviço de IA não respondeu). Tente de novo em instantes.';
                $this->gravar($conversa, 'assistant', $falha);

                return ['resposta' => $falha, 'protocolo' => null];
            }

            $msg = $r['mensagem'];
            $chamadas = $msg['tool_calls'] ?? [];
            if (! $chamadas) {
                $resposta = trim((string) ($msg['content'] ?? '')) ?: 'Pode me contar um pouco mais do que está acontecendo?';
                $this->gravar($conversa, 'assistant', $resposta, null, null, null, $r['tokens']);
                $conversa->touch();

                return ['resposta' => $resposta, 'protocolo' => $protocolo];
            }

            $mensagens[] = ['role' => 'assistant', 'content' => $msg['content'] ?? '', 'tool_calls' => $chamadas];
            $this->gravar($conversa, 'assistant', $msg['content'] ?? null, $chamadas, null, null, $r['tokens']);

            foreach ($chamadas as $c) {
                $nome = (string) ($c['function']['name'] ?? '');
                $args = json_decode((string) ($c['function']['arguments'] ?? '{}'), true) ?: [];
                if ($nome === 'abrir_chamado') {
                    $res = $this->chamado->abrir(
                        $user, $conversa,
                        (string) ($args['assunto'] ?? ''), (string) ($args['descricao'] ?? ''),
                        (string) ($args['prioridade'] ?? 'medium'), $pagina
                    );
                    if ($res['ok'] ?? false) {
                        $protocolo = $res['protocolo'] ?? null;
                    }
                } else {
                    $res = $ferr->executar($nome, $args);
                }
                $json = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $mensagens[] = ['role' => 'tool', 'tool_call_id' => (string) ($c['id'] ?? ''), 'content' => $json];
                $this->gravar($conversa, 'tool', $json, ['nome' => $nome, 'args' => $args], (string) ($c['id'] ?? ''));
            }
        }

        return ['resposta' => 'Não consegui concluir. Quer que eu abra um chamado para a equipe?', 'protocolo' => $protocolo];
    }

    private function instrucoes(User $user, ?string $pagina): string
    {
        $empresa = $user->empresa;
        $loja = session('unidade_id') ? \App\Models\Unidade::withoutGlobalScopes()->where('id', session('unidade_id'))->value('nome') : null;
        $perfil = $user->perfil?->value ?? (string) $user->perfil;
        $prazo = $empresa ? FiadoService::nome((int) $empresa->id) : 'Crediário';
        $agora = now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i');
        $semEmpresa = $empresa ? '' : "\n- Este usuário é da equipe da plataforma e não está dentro de nenhuma empresa: você não consulta dados, só explica pelo manual.";

        return <<<TXT
Você é o assistente de ajuda do ERP Comercial IA365, dentro do próprio sistema. Fala com quem está usando o ERP agora.

QUEM ESTÁ FALANDO
- Nome: {$user->name} · perfil: {$perfil}
- Empresa: {$this->nomeEmpresa($empresa)} · loja selecionada: {$this->ou($loja)}
- Tela aberta agora: {$this->ou($pagina)}
- Agora: {$agora} (horário de Brasília){$semEmpresa}

COMO RESPONDER
- Português do Brasil, simples, curto e direto, como um colega de suporte paciente. Sem jargão técnico, sem nome de tabela, rota, classe ou código de programação.
- Procedimento = passo a passo numerado com o caminho do menu e o nome exato dos botões (ex.: "Menu Produtos → Promoções → Nova promoção").
- ANTES de explicar qualquer procedimento ou erro, chame buscar_ajuda (pode chamar mais de uma vez). Responda com base no que o manual trouxe. Se o manual não cobre, diga que não encontrou — NUNCA invente tela, botão, menu ou regra.
- Perguntas vagas ("está com erro"): pergunte UMA coisa objetiva (qual tela, qual mensagem apareceu, qual número da nota/venda) — ou já consulte os dados se der para descobrir sozinho.
- Use o nome que esta loja dá à venda a prazo direto com a loja: "{$prazo}". Não use outro nome para isso.
- Só assuntos do ERP. Outros assuntos (contabilidade, lei, preço do sistema, outros softwares): diga com educação que só ajuda com o uso do ERP; dúvida de qual código fiscal usar (NCM, CST, CFOP) é do contador da empresa — explique onde gravar no ERP.

DADOS DA EMPRESA
- Você só enxerga os dados DESTA empresa e só o que o perfil do usuário pode ver na tela, pelas ferramentas. Nunca fale de outras empresas nem diga que existem.
- Nunca peça nem mostre senha, token, CSC, arquivo/senha do certificado.
- Você só consulta; não altera nada. Quem corrige é a pessoa, na tela — você ensina o caminho.

"ERRO NESTA NOTA"
1. Ache a nota (consultar_notas; se a pessoa não disse o número, olhe as rejeitadas/pendentes mais recentes e confirme qual é).
2. detalhar_nota para ler a mensagem da SEFAZ/Focus, os itens e o cadastro do cliente.
3. buscar_ajuda com o código/texto da rejeição.
4. Explique em uma frase o que a SEFAZ recusou, depois o passo a passo para corrigir (qual produto/cliente/configuração e qual campo) e como emitir de novo.

CHAMADO PARA A EQUIPE IA365
- Abra (abrir_chamado) quando: parece defeito do sistema; a mensagem é de erro interno/inesperado; o manual não resolve depois de tentar; ou a pessoa pede para falar com o suporte.
- NÃO ofereça chamado quando a solução é a própria pessoa corrigir um cadastro ou configuração que você já explicou.
- Para citar venda ou nota, use sempre o número que aparece na tela (numero / numero_da_venda), nunca ids internos.
- Antes de abrir, confirme com a pessoa em uma frase ("Quer que eu abra um chamado para a equipe da IA365?") — exceto se ela mesma pediu. Não abra dois chamados para o mesmo assunto na conversa.
- No chamado, escreva tudo o que você já descobriu (tela, mensagem exata, nota/venda/produto, o que já foi conferido) para a equipe não precisar perguntar de novo.
- Depois de abrir, informe o número do protocolo e diga que a equipe da IA365 vai responder; o acompanhamento é pelo suporte.
TXT;
    }

    private function nomeEmpresa($empresa): string
    {
        return $empresa ? (string) ($empresa->nome_fantasia ?: $empresa->razao_social) : '—';
    }

    private function ou(?string $v): string
    {
        return $v ?: '—';
    }

    private function historico(AjudaConversa $conversa): array
    {
        $n = (int) config('ajuda.historico_mensagens', 16);

        return $conversa->mensagens()
            ->whereIn('papel', ['user', 'assistant'])
            ->whereNotNull('conteudo')->where('conteudo', '!=', '')
            ->whereNull('ferramentas')
            ->orderByDesc('id')->limit($n)->get()->reverse()
            ->map(fn (AjudaMensagem $m) => ['role' => $m->papel, 'content' => (string) $m->conteudo])
            ->values()->all();
    }

    /** @return array{ok:bool, mensagem?:array, tokens?:int} */
    private function chamarIa(array $mensagens, array $ferramentas): array
    {
        $c = config('ajuda.llm');
        if (empty($c['api_key'])) {
            Log::error('[Ajuda] sem chave da IA (AJUDA_LLM_API_KEY)');

            return ['ok' => false];
        }
        $corpo = [
            'model' => $c['modelo'],
            'messages' => $mensagens,
            'temperature' => $c['temperatura'],
            'max_tokens' => $c['max_tokens'],
        ];
        if ($ferramentas) {
            $corpo['tools'] = $ferramentas;
            $corpo['tool_choice'] = 'auto';
        }

        try {
            $r = Http::withToken($c['api_key'])->acceptJson()->timeout($c['timeout'])
                ->retry(2, 800, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, false)
                ->post($c['base_url'] . '/chat/completions', $corpo);
        } catch (\Throwable $e) {
            Log::warning('[Ajuda] IA não respondeu', ['erro' => $e->getMessage()]);

            return ['ok' => false];
        }
        if (! $r->successful()) {
            Log::warning('[Ajuda] IA recusou', ['status' => $r->status(), 'corpo' => Str::limit($r->body(), 500)]);

            return ['ok' => false];
        }
        $msg = $r->json('choices.0.message');
        if (! is_array($msg)) {
            return ['ok' => false];
        }

        return ['ok' => true, 'mensagem' => $msg, 'tokens' => (int) $r->json('usage.total_tokens')];
    }

    private function gravar(AjudaConversa $conversa, string $papel, ?string $conteudo, ?array $ferramentas = null, ?string $toolCallId = null, ?string $pagina = null, ?int $tokens = null): void
    {
        AjudaMensagem::create([
            'conversa_id' => $conversa->id,
            'empresa_id' => $conversa->empresa_id,
            'papel' => $papel,
            'conteudo' => $conteudo,
            'ferramentas' => $ferramentas,
            'tool_call_id' => $toolCallId,
            'pagina' => $pagina ? Str::limit($pagina, 250, '') : null,
            'tokens' => $tokens,
        ]);
    }
}
