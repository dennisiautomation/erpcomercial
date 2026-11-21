<?php

namespace App\Services\FocusNFe;

use App\Enums\StatusNotaFiscal;
use App\Exceptions\NotaFiscalCancelException;
use App\Models\NotaFiscal;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Regra ÚNICA do resultado de um cancelamento na Focus (NF-e, NFS-e municipal e
 * NFS-e Nacional — a NFC-e já seguia esta regra no próprio NFCeService).
 *
 * HTTP 200 NÃO significa cancelado: a Focus responde 200 com status
 * "erro_cancelamento"/"autorizado" quando a SEFAZ/prefeitura recusa (ex.: prazo
 * vencido). Só marca cancelada com confirmação explícita ("cancelado"); senão
 * guarda a mensagem na nota e lança NotaFiscalCancelException com texto para a tela.
 * (Levantamento de 21/11/2026: NF-e e NFS-e diziam "cancelada com sucesso" com
 * qualquer 200 — nota viva na SEFAZ e cancelada no ERP.)
 */
class ConfirmacaoCancelamento
{
    public static function aplicar(Response $response, NotaFiscal $nota, string $justificativa, string $rotulo): NotaFiscal
    {
        $data = $response->json() ?? [];
        $status = strtolower((string) ($data['status'] ?? ''));

        if ($response->successful() && in_array($status, ['cancelado', 'cancelada'], true)) {
            $nota->status = StatusNotaFiscal::Cancelada;
            $nota->focus_status = $data['status'];
            $nota->cancelamento_motivo = $justificativa;
            $nota->cancelamento_protocolo = $data['protocolo'] ?? $data['numero_protocolo'] ?? $nota->cancelamento_protocolo;
            $nota->cancelada_em = now();
            $nota->save();
            Log::info("{$rotulo}: cancelada", ['ref' => $nota->focus_ref]);

            return $nota;
        }

        if ($response->successful() && str_contains($status, 'processando')) {
            $nota->focus_status = $data['status'];
            $nota->save();
            throw new NotaFiscalCancelException("O pedido de cancelamento da {$rotulo} foi enviado e ainda está em processamento. Clique em \"Consultar\" na nota daqui a alguns minutos para ver se foi aceito.");
        }

        $raw = self::mensagem($data) ?: ($status !== '' ? "não confirmou o cancelamento (situação: {$status})." : 'erro desconhecido ao cancelar.');
        $nota->focus_mensagem = $raw;
        $nota->save();
        Log::warning("{$rotulo}: cancelamento recusado", ['ref' => $nota->focus_ref, 'http' => $response->status(), 'resposta' => $data]);

        throw new NotaFiscalCancelException(self::traduzir($raw, $response->status(), $rotulo));
    }

    public static function mensagem(array $data): string
    {
        $m = $data['mensagem_sefaz'] ?? $data['mensagem'] ?? ($data['erros'][0]['mensagem'] ?? null);
        if (is_array($m)) {
            $m = json_encode($m, JSON_UNESCAPED_UNICODE);
        }

        return trim((string) $m);
    }

    public static function traduzir(string $raw, int $http, string $rotulo): string
    {
        $l = mb_strtolower($raw);
        if (str_contains($l, 'prazo') || str_contains($l, 'exced')) {
            return "O prazo para cancelar esta {$rotulo} já passou. Faça uma nota de devolução (ou fale com o contador).";
        }
        if (str_contains($l, 'já cancel') || str_contains($l, 'ja cancel') || str_contains($l, 'duplicidade de evento')) {
            return "Esta {$rotulo} já foi cancelada antes. Clique em \"Consultar\" para atualizar a situação.";
        }
        if (str_contains($l, 'certificado')) {
            return 'Certificado digital inválido ou vencido. Atualize o certificado na Configuração Fiscal.';
        }
        if ($http === 401 || str_contains($l, 'token')) {
            return 'A ligação com o emissor de notas (Focus) não está autorizada. Fale com o suporte da IA365.';
        }
        if ($http >= 500) {
            return 'A SEFAZ/prefeitura está instável agora. Aguarde alguns minutos e tente de novo.';
        }

        return "A {$rotulo} não foi cancelada: {$raw}";
    }
}
