<?php

namespace App\Services\FocusNFe;

use App\Models\NFeEvento;
use App\Models\NotaFiscal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Evento 110192 — Insucesso de Entrega (NT 2021.002).
 *
 * Registra que a mercadoria não conseguiu ser entregue ao destinatário.
 * Permite que o transportador retorne com a carga sem ter que emitir
 * nova NF-e (antes era preciso uma NF-e de devolução complicada).
 *
 * Motivos aceitos pela SEFAZ:
 *   1 — Sem funcionamento do estabelecimento
 *   2 — Recusa do destinatário
 *   3 — Endereço não encontrado / inexistente
 *   4 — Outros
 *
 * Endpoint Focus: POST /v2/nfe/{ref}/insucesso_entrega
 */
class InsucessoEntregaService
{
    public function __construct(private readonly FocusNFeClient $client) {}

    /**
     * @param  array{
     *     data_tentativa?:string,
     *     motivo:int,
     *     justificativa?:string,
     *     latitude?:float,
     *     longitude?:float,
     *     hash_tentativa?:string
     * }  $dados
     */
    public function registrar(NotaFiscal $nota, array $dados, ?int $userId = null): NFeEvento
    {
        $this->validarNotaAutorizada($nota);
        $dados = $this->validarDados($dados);

        $proximaSequencia = NFeEvento::withoutGlobalScopes()
            ->where('nota_fiscal_id', $nota->id)
            ->where('tipo', NFeEvento::TIPO_INSUCESSO_ENTREGA)
            ->max('sequencia') + 1;

        $evento = NFeEvento::create([
            'empresa_id'    => $nota->empresa_id,
            'unidade_id'    => $nota->unidade_id,
            'nota_fiscal_id' => $nota->id,
            'tipo'          => NFeEvento::TIPO_INSUCESSO_ENTREGA,
            'sequencia'     => $proximaSequencia,
            'dados'         => $dados,
            'status'        => NFeEvento::STATUS_PENDENTE,
            'criado_por'    => $userId,
        ]);

        try {
            $payload = array_filter([
                'data_tentativa_entrega' => $dados['data_tentativa'],
                'motivo_insucesso' => $dados['motivo'],
                'justificativa' => $dados['justificativa'] ?? null,
                'latitude' => $dados['latitude'] ?? null,
                'longitude' => $dados['longitude'] ?? null,
                'hash_tentativa_entrega' => $dados['hash_tentativa'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            $response = $this->client->post(
                "/v2/nfe/{$nota->focus_ref}/insucesso_entrega",
                $payload,
            );

            $data = $response->json() ?? [];

            return DB::transaction(function () use ($evento, $response, $data) {
                if ($response->successful() && ($data['status'] ?? '') === 'autorizado') {
                    $evento->update([
                        'status' => NFeEvento::STATUS_AUTORIZADO,
                        'protocolo' => $data['numero_protocolo'] ?? $data['protocolo'] ?? null,
                        'xml_url' => $data['caminho_xml'] ?? null,
                        'mensagem_retorno' => $data['mensagem'] ?? 'Autorizado.',
                    ]);
                } else {
                    $mensagem = $data['mensagem'] ?? $data['erros'] ?? 'Erro não especificado.';
                    $evento->update([
                        'status' => NFeEvento::STATUS_REJEITADO,
                        'mensagem_retorno' => is_array($mensagem) ? json_encode($mensagem) : $mensagem,
                    ]);
                }
                return $evento->fresh();
            });
        } catch (\Throwable $e) {
            Log::error('[InsucessoEntrega] erro', [
                'evento_id' => $evento->id,
                'erro' => $e->getMessage(),
            ]);
            $evento->update([
                'status' => NFeEvento::STATUS_REJEITADO,
                'mensagem_retorno' => 'Falha na comunicação: ' . $e->getMessage(),
            ]);
            throw new RuntimeException(
                'Não foi possível registrar o insucesso de entrega agora. Tente novamente.'
            );
        }
    }

    /** @return array<string, mixed> */
    private function validarDados(array $dados): array
    {
        $motivo = (int) ($dados['motivo'] ?? 0);
        if (! in_array($motivo, [1, 2, 3, 4], true)) {
            throw new RuntimeException(
                'Motivo inválido. Use 1 (sem funcionamento), 2 (recusa), 3 (endereço não encontrado) ou 4 (outros).'
            );
        }

        $data = $dados['data_tentativa'] ?? now()->format('Y-m-d\TH:i:sP');
        try {
            Carbon::parse($data);
        } catch (\Throwable) {
            throw new RuntimeException('Data da tentativa de entrega inválida.');
        }

        $justificativa = trim((string) ($dados['justificativa'] ?? ''));
        if ($motivo === 4 && mb_strlen($justificativa) < 15) {
            throw new RuntimeException(
                'Para motivo "Outros" a justificativa é obrigatória e precisa ter ao menos 15 caracteres.'
            );
        }

        return [
            'data_tentativa' => $data,
            'motivo' => $motivo,
            'justificativa' => $justificativa ?: null,
            'latitude' => isset($dados['latitude']) ? (float) $dados['latitude'] : null,
            'longitude' => isset($dados['longitude']) ? (float) $dados['longitude'] : null,
            'hash_tentativa' => $dados['hash_tentativa'] ?? null,
        ];
    }

    private function validarNotaAutorizada(NotaFiscal $nota): void
    {
        if ($nota->tipo?->value !== 'nfe') {
            throw new RuntimeException('Insucesso de Entrega é um evento específico de NF-e.');
        }
        if ($nota->status?->value !== 'autorizada') {
            throw new RuntimeException('Só é possível registrar Insucesso de Entrega em NF-e autorizada.');
        }
        if (empty($nota->focus_ref)) {
            throw new RuntimeException('NF-e sem referência Focus.');
        }
    }
}
