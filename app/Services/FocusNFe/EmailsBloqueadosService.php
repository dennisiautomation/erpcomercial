<?php

namespace App\Services\FocusNFe;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Consulta/remove e-mails que a Focus NFe bloqueou por bounce/spam.
 *
 * A Focus envia DANFE/XML por email aos destinatários das notas. Se o email
 * dá bounce, cai em spam ou é marcado como inválido, a Focus adiciona a
 * uma blocklist e para de tentar. Isso economiza recursos, mas também
 * silencia entregas reais quando o email do cliente foi corrigido.
 *
 * Endpoint Focus:
 *   GET    /v2/emails_bloqueados?cnpj={cnpj}
 *   DELETE /v2/emails_bloqueados/{email}
 *
 * Precisa do token da empresa (não do master) para identificar a conta.
 */
class EmailsBloqueadosService
{
    public function __construct(private readonly FocusNFeClient $client) {}

    /**
     * @return array<int, array{email:string, motivo:string, bloqueado_em:?string}>
     */
    public function listar(): array
    {
        $response = $this->client->get('/v2/emails_bloqueados');

        if (! $response->successful()) {
            Log::warning('[EmailsBloqueados] falha ao listar', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException(
                'Focus NFe não respondeu à listagem de emails bloqueados (HTTP ' . $response->status() . ').'
            );
        }

        $itens = (array) ($response->json() ?? []);

        // Normaliza resposta para estrutura previsível
        return array_map(function ($item) {
            return [
                'email' => $item['email'] ?? '',
                'motivo' => $item['motivo'] ?? $item['razao'] ?? 'não informado',
                'bloqueado_em' => $item['data_bloqueio'] ?? $item['bloqueado_em'] ?? null,
            ];
        }, $itens);
    }

    /**
     * Remove o email da blocklist — a Focus volta a tentar entregar para ele.
     */
    public function desbloquear(string $email): void
    {
        $email = trim(strtolower($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email inválido.');
        }

        $response = $this->client->delete('/v2/emails_bloqueados/' . urlencode($email));

        // 404 = email não está bloqueado → tratamos como sucesso idempotente
        if (! $response->successful() && $response->status() !== 404) {
            Log::warning('[EmailsBloqueados] falha ao desbloquear', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException(
                'Focus NFe recusou o desbloqueio (HTTP ' . $response->status() . '): '
                . ($response->json('mensagem') ?? $response->body())
            );
        }

        Log::info('[EmailsBloqueados] email desbloqueado', ['email' => $email]);
    }
}
