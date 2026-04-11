<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\StatusNotaFiscal;
use App\Http\Controllers\Controller;
use App\Models\NotaFiscal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FocusNFeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Webhook Focus NFe recebido', $request->all());

        $ref = $request->input('ref') ?? $request->input('cnpj_emitente');
        $status = $request->input('status');

        if (! $ref) {
            Log::warning('Webhook Focus NFe sem referencia', $request->all());
            return response()->json(['message' => 'Referencia nao encontrada.'], 200);
        }

        $nota = NotaFiscal::where('focus_ref', $ref)->first();

        if (! $nota) {
            Log::warning('Webhook Focus NFe: nota nao encontrada', ['ref' => $ref]);
            return response()->json(['message' => 'Nota nao encontrada.'], 200);
        }

        // Mapear status do Focus NFe para nosso enum
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

        // Atualizar campos extras se disponiveis
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
}
