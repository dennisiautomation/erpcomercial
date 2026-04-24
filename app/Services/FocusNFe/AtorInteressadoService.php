<?php

namespace App\Services\FocusNFe;

use App\Models\NFeEvento;
use App\Models\NotaFiscal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Evento 110150 — Ator Interessado na NF-e.
 *
 * Permite adicionar CNPJs de interessados (transportadora, marketplace,
 * seguradora, etc.) a uma NF-e já autorizada. Cada novo evento substitui
 * ou complementa a lista de atores anteriores — a SEFAZ trata por sequência.
 *
 * Endpoint Focus: POST /v2/nfe/{ref}/ator_interessado
 * Corpo esperado pela Focus:
 *   {
 *     "cnpj_autorizado": "12345678000199",
 *     "tipo_autorizado": 1   // 1=transportador 2=autor do fato
 *   }
 *
 * Na nossa API recebemos os dados do usuário e persistimos como NFeEvento
 * com status pendente → autorizado após a Focus confirmar.
 */
class AtorInteressadoService
{
    public function __construct(private readonly FocusNFeClient $client) {}

    /**
     * Registra um ator interessado para uma nota já autorizada.
     *
     * @param  array{cnpj_ator:string, tipo_ator?:int, razao_social_ator?:string}  $dados
     */
    public function registrar(NotaFiscal $nota, array $dados, ?int $userId = null): NFeEvento
    {
        $this->validarNotaAutorizada($nota);
        $dados = $this->validarDados($dados);

        // Calcula próxima sequência
        $proximaSequencia = NFeEvento::withoutGlobalScopes()
            ->where('nota_fiscal_id', $nota->id)
            ->where('tipo', NFeEvento::TIPO_ATOR_INTERESSADO)
            ->max('sequencia') + 1;

        $evento = NFeEvento::create([
            'empresa_id'    => $nota->empresa_id,
            'unidade_id'    => $nota->unidade_id,
            'nota_fiscal_id' => $nota->id,
            'tipo'          => NFeEvento::TIPO_ATOR_INTERESSADO,
            'sequencia'     => $proximaSequencia,
            'dados'         => $dados,
            'status'        => NFeEvento::STATUS_PENDENTE,
            'criado_por'    => $userId,
        ]);

        try {
            $payload = [
                'cnpj_autorizado' => $dados['cnpj_ator'],
                'tipo_autorizado' => $dados['tipo_ator'] ?? 1, // default: transportador
            ];

            Log::info('[AtorInteressado] enviando à Focus', [
                'nota_id' => $nota->id,
                'evento_id' => $evento->id,
                'sequencia' => $proximaSequencia,
            ]);

            $response = $this->client->post(
                "/v2/nfe/{$nota->focus_ref}/ator_interessado",
                $payload,
            );

            $data = $response->json() ?? [];

            return DB::transaction(function () use ($evento, $response, $data) {
                if ($response->successful() && ($data['status'] ?? '') === 'autorizado') {
                    $evento->update([
                        'status' => NFeEvento::STATUS_AUTORIZADO,
                        'protocolo' => $data['numero_protocolo'] ?? $data['protocolo'] ?? null,
                        'xml_url' => $data['caminho_xml'] ?? null,
                        'mensagem_retorno' => $data['mensagem'] ?? 'Autorizado pela SEFAZ.',
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
            Log::error('[AtorInteressado] erro de comunicação', [
                'evento_id' => $evento->id,
                'erro' => $e->getMessage(),
            ]);
            $evento->update([
                'status' => NFeEvento::STATUS_REJEITADO,
                'mensagem_retorno' => 'Falha na comunicação com Focus: ' . $e->getMessage(),
            ]);
            throw new RuntimeException(
                'Não foi possível registrar o ator interessado agora. Tente novamente em instantes.'
            );
        }
    }

    /** @return array<string, mixed> */
    private function validarDados(array $dados): array
    {
        $cnpj = preg_replace('/\D+/', '', (string) ($dados['cnpj_ator'] ?? ''));

        if (strlen($cnpj) !== 14) {
            throw new RuntimeException('CNPJ do ator interessado inválido (precisa ter 14 dígitos).');
        }

        $tipo = (int) ($dados['tipo_ator'] ?? 1);
        if (! in_array($tipo, [1, 2, 3, 4], true)) {
            throw new RuntimeException(
                'Tipo de ator inválido. Use 1=Transportador, 2=Autor do fato, 3=Marketplace, 4=Outro.'
            );
        }

        return [
            'cnpj_ator' => $cnpj,
            'tipo_ator' => $tipo,
            'razao_social_ator' => trim((string) ($dados['razao_social_ator'] ?? '')),
        ];
    }

    private function validarNotaAutorizada(NotaFiscal $nota): void
    {
        if ($nota->tipo?->value !== 'nfe') {
            throw new RuntimeException('Ator Interessado é um evento específico de NF-e.');
        }

        if ($nota->status?->value !== 'autorizada') {
            throw new RuntimeException(
                'Só é possível registrar Ator Interessado em NF-e autorizada.'
            );
        }

        if (empty($nota->focus_ref)) {
            throw new RuntimeException('NF-e sem referência Focus — evento não pode ser enviado.');
        }
    }
}
