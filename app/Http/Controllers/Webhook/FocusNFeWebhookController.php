<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\StatusNotaFiscal;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint que recebe webhooks (gatilhos) da Focus NFe.
 *
 * Segurança:
 *  - Cada unidade tem um webhook_secret único guardado em configuracoes_fiscais.
 *  - Quando cadastramos o gatilho via POST /v2/hooks passamos
 *    `authorization = "Bearer <webhook_secret>"`.
 *  - A Focus repete esse header em cada chamada para cá; nós validamos aqui.
 *  - Se o secret não bater, retorna 401 e loga como tentativa suspeita.
 *
 * O webhook pode chegar para:
 *  - NFe/NFCe (focus_ref = nossa referência)
 *  - NFSe (focus_ref)
 *  - Manifestação do destinatário (cnpj_emitente = CNPJ da nossa unidade)
 *  - CC-e (ref + evento=carta_correcao)
 *
 * Resposta 2xx obrigatória (se não a Focus reentrega por 6h com backoff).
 */
class FocusNFeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->all();

        Log::info('Webhook Focus NFe recebido', $payload + ['ip' => $request->ip()]);

        // Tenta localizar config pelo cnpj_emitente (quando a Focus envia)
        $config = $this->resolveConfig($request);

        // Se temos ref, tenta achar a nota — se config não veio via cnpj, cai na unidade da nota
        $ref = $request->input('ref');
        $nota = $ref ? NotaFiscal::withoutGlobalScopes()->where('focus_ref', $ref)->first() : null;

        if (! $config && $nota) {
            $config = ConfiguracaoFiscal::withoutGlobalScopes()
                ->where('empresa_id', $nota->empresa_id)
                ->where('unidade_id', $nota->unidade_id)
                ->first();
        }

        // Valida Authorization apenas quando a config tem webhook_secret definido.
        // Unidades antigas (sem secret) continuam aceitas — o modelo revenda gera secret
        // em todo novo cadastro; o legado funciona em modo permissivo.
        if ($config && ! empty($config->webhook_secret) && ! $this->authorizationValid($request, $config)) {
            Log::warning('[WebhookFocus] assinatura inválida', [
                'empresa_id' => $config->empresa_id,
                'unidade_id' => $config->unidade_id,
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Roteia por tipo de evento
        $evento = $request->input('evento');

        if ($evento === 'manifestacao_destinatario' || ($request->filled('cnpj_emitente') && ! $ref)) {
            if (! $config) {
                Log::warning('Webhook Focus NFe sem referencia', $payload);
                return response()->json(['message' => 'Referencia nao encontrada.'], 200);
            }
            return $this->handleManifestacao($request, $config);
        }

        return $this->handleNota($request, $nota);
    }

    // ─── Handlers específicos ──────────────────────────────────────────

    private function handleNota(Request $request, ?NotaFiscal $nota): Response
    {
        $ref = $request->input('ref');

        if (! $ref) {
            Log::warning('Webhook Focus NFe sem referencia', $request->all());
            return response()->json(['message' => 'Referencia nao encontrada.'], 200);
        }

        if (! $nota) {
            Log::warning('Webhook Focus NFe: nota nao encontrada', ['ref' => $ref]);
            return response()->json(['message' => 'Nota nao encontrada.'], 200);
        }

        $status = $request->input('status');

        $novoStatus = match ($status) {
            'autorizado', 'autorizada' => StatusNotaFiscal::Autorizada,
            'cancelado', 'cancelada'   => StatusNotaFiscal::Cancelada,
            'erro_autorizacao', 'rejeitado', 'rejeitada' => StatusNotaFiscal::Rejeitada,
            default => null,
        };

        $updateData = [
            'focus_status'   => $status,
            'focus_mensagem' => $request->input('mensagem') ?? $request->input('motivo') ?? null,
        ];

        if ($novoStatus) {
            $updateData['status'] = $novoStatus;
        }

        if ($request->filled('chave_nfe')) {
            $updateData['chave_acesso'] = $request->input('chave_nfe');
        }

        if ($request->filled('numero')) {
            $updateData['numero'] = $request->input('numero');
        }

        if ($request->filled('caminho_xml_nota_fiscal')) {
            $updateData['xml_url'] = $request->input('caminho_xml_nota_fiscal');
        }

        if ($request->filled('caminho_danfe')) {
            $updateData['danfe_url'] = $request->input('caminho_danfe');
        }

        if ($novoStatus === StatusNotaFiscal::Autorizada && ! $nota->emitida_em) {
            $updateData['emitida_em'] = now();
        }

        if ($novoStatus === StatusNotaFiscal::Cancelada) {
            $updateData['cancelada_em'] = now();
            if ($request->filled('protocolo_cancelamento')) {
                $updateData['cancelamento_protocolo'] = $request->input('protocolo_cancelamento');
            }
        }

        $nota->update($updateData);

        Log::info('Webhook Focus NFe processado', [
            'nota_id' => $nota->id,
            'status'  => $novoStatus?->value ?? $status,
        ]);

        return response()->json(['message' => 'OK'], 200);
    }

    private function handleManifestacao(Request $request, ConfiguracaoFiscal $config): Response
    {
        // Quando chega uma NFe recebida nova, dispara sync para trazer os detalhes.
        // (Mantemos lógica simples aqui; SincronizarNFesRecebidasJob busca e salva.)
        Log::info('[WebhookFocus] evento manifestação — disparando sync', [
            'unidade_id' => $config->unidade_id,
        ]);

        if ($config->unidade_id) {
            \App\Jobs\SincronizarNFesRecebidasJob::dispatch($config->empresa_id, $config->unidade_id)
                ->onQueue('fiscal');
        }

        return response()->json(['message' => 'OK'], 200);
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function resolveConfig(Request $request): ?ConfiguracaoFiscal
    {
        $cnpj = preg_replace(
            '/\D+/',
            '',
            (string) ($request->input('cnpj_emitente') ?? $request->input('cnpj') ?? '')
        );

        if (! $cnpj) {
            return null;
        }

        // Procura a unidade que tem esse CNPJ (ou a empresa, caso a unidade não tenha)
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->whereHas('unidade', fn ($q) => $q->whereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = ?", [$cnpj]))
            ->with(['unidade', 'empresa'])
            ->first();

        if ($config) {
            return $config;
        }

        // Fallback: match pelo CNPJ da empresa
        return ConfiguracaoFiscal::withoutGlobalScopes()
            ->whereHas('empresa', fn ($q) => $q->whereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = ?", [$cnpj]))
            ->with(['unidade', 'empresa'])
            ->first();
    }

    private function authorizationValid(Request $request, ConfiguracaoFiscal $config): bool
    {
        $secret = $config->webhook_secret;

        // Unidade criada antes do modelo revenda: aceita sem header (modo legado).
        // Aviso é logado para migrar para o modelo novo.
        if (empty($secret)) {
            Log::notice('[WebhookFocus] config sem webhook_secret — aceitando sem validação', [
                'empresa_id' => $config->empresa_id,
                'unidade_id' => $config->unidade_id,
            ]);
            return true;
        }

        $header = (string) $request->header('Authorization', '');
        $esperado = 'Bearer ' . $secret;

        return hash_equals($esperado, $header);
    }
}
