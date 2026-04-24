<?php

namespace App\Services\FocusNFe;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Backup mensal de XMLs fiscais (NFe/NFCe/NFSe autorizadas e canceladas).
 *
 * A Focus NFe guarda XMLs por 5 anos, mas é obrigação do contribuinte ter
 * os próprios backups. O endpoint /v2/backups monta um arquivo ZIP de um
 * mês inteiro, incluindo notas emitidas e recebidas quando configurado.
 *
 * Endpoint Focus:
 *   POST /v2/backups?cnpj={cnpj}&mes=YYYY-MM
 *     → retorna {status: "processando", url: "..."} ou 202/200
 *   GET  /v2/backups?cnpj={cnpj}&mes=YYYY-MM
 *     → retorna status atual + url do zip quando pronto
 *
 * A geração é assíncrona (pode demorar minutos); a UI exibe o estado.
 */
class BackupXmlService
{
    public function __construct(private readonly FocusNFeClient $client) {}

    /**
     * Solicita geração de um backup para o mês indicado (YYYY-MM).
     *
     * @return array{status: string, url: ?string, mes: string, solicitado_em: string}
     */
    public function solicitar(string $mes): array
    {
        $mes = $this->normalizarMes($mes);

        $response = $this->client->post('/v2/backups', ['mes' => $mes]);

        if (! $response->successful() && $response->status() !== 202) {
            throw new RuntimeException(
                'Focus NFe recusou a solicitação de backup (HTTP ' . $response->status() . '): '
                . ($response->json('mensagem') ?? $response->body())
            );
        }

        $data = $response->json() ?? [];

        Log::info('[BackupXml] solicitação aceita', [
            'mes' => $mes,
            'status' => $data['status'] ?? 'solicitado',
        ]);

        return [
            'status' => $data['status'] ?? 'processando',
            'url' => $data['url'] ?? $data['caminho_arquivo'] ?? null,
            'mes' => $mes,
            'solicitado_em' => now()->toIso8601String(),
        ];
    }

    /**
     * Consulta status de um backup já solicitado.
     *
     * @return array{status: string, url: ?string, mes: string}
     */
    public function consultar(string $mes): array
    {
        $mes = $this->normalizarMes($mes);

        $response = $this->client->get('/v2/backups', ['mes' => $mes]);

        if (! $response->successful()) {
            return [
                'status' => 'indisponivel',
                'url' => null,
                'mes' => $mes,
            ];
        }

        $data = $response->json() ?? [];

        return [
            'status' => $data['status'] ?? 'desconhecido',
            'url' => $data['url'] ?? $data['caminho_arquivo'] ?? null,
            'mes' => $mes,
        ];
    }

    /**
     * Retorna os últimos 12 meses (YYYY-MM) em ordem decrescente —
     * usado pela UI para oferecer quais meses o usuário pode baixar.
     *
     * @return array<int, string>
     */
    public function mesesDisponiveis(int $quantidade = 12): array
    {
        $base = Carbon::now()->startOfMonth()->subMonth();
        $meses = [];

        for ($i = 0; $i < $quantidade; $i++) {
            $meses[] = $base->copy()->subMonths($i)->format('Y-m');
        }

        return $meses;
    }

    private function normalizarMes(string $mes): string
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            throw new RuntimeException(
                "Mês inválido: '{$mes}'. Use o formato YYYY-MM (ex: 2026-03)."
            );
        }
        return $mes;
    }
}
