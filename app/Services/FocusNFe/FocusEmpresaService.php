<?php

namespace App\Services\FocusNFe;

use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\Unidade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gerencia empresas-filhas na Focus NFe (modelo revenda).
 *
 * Usa o token master (Token Principal de Produção) via FocusNFeClient::master()
 * para operar o endpoint /v2/empresas da Focus:
 *
 *   POST   /v2/empresas              - cria empresa (retorna id + tokens)
 *   PUT    /v2/empresas/{id}         - atualiza dados da empresa
 *   GET    /v2/empresas/{id}         - consulta uma empresa específica
 *   GET    /v2/empresas              - lista todas as empresas
 *
 * Cada unidade do ERP vira uma "empresa" na Focus (CNPJ único).
 * A Focus gera um token_producao e token_homologacao por empresa-filha,
 * que guardamos em configuracoes_fiscais e usamos para emitir notas.
 *
 * Após criar, é possível:
 *   - Subir certificado A1 via CertificadoDigitalService
 *   - Criar webhooks via FocusEmpresaService::cadastrarWebhook()
 *   - Emitir notas com FocusNFeClient::fromConfig($config)
 */
class FocusEmpresaService
{
    public function __construct(
        private readonly FocusNFeClient $master,
    ) {}

    public static function make(): static
    {
        return new static(FocusNFeClient::master());
    }

    // ─── CRUD empresas ─────────────────────────────────────────────────

    /**
     * Cria a empresa-filha na Focus e persiste os tokens em configuracoes_fiscais.
     *
     * $flags controla os tipos de documento habilitados:
     *   - habilita_nfe (default false)
     *   - habilita_nfce (default false)
     *   - habilita_nfse (default false)
     *   - habilita_manifestacao (default false)
     *   - habilita_contingencia_offline_nfce (default false)
     *   - habilita_cte (default false)
     *
     * Retorna o array com campos retornados pela Focus (inclui id, token_producao, token_homologacao).
     *
     * @param  array<string, bool>  $flags
     * @return array<string, mixed>
     */
    public function criar(Empresa $empresa, Unidade $unidade, array $flags = []): array
    {
        $payload = $this->montarPayload($empresa, $unidade, $flags);

        $response = $this->master->post('/v2/empresas', $payload);

        if ($response->failed()) {
            $this->handleError($response, 'criar empresa');
        }

        $data = $response->json();

        // Persiste no nosso banco
        $this->persistirNaConfiguracao($empresa, $unidade, $data, $flags);

        Log::info('[FocusEmpresa] empresa criada', [
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'focus_empresa_id' => $data['id'] ?? null,
            'cnpj' => $unidade->cnpj ?: $empresa->cnpj,
        ]);

        return $data;
    }

    /**
     * Atualiza dados da empresa-filha na Focus.
     *
     * @param  array<string, bool>  $flags
     * @return array<string, mixed>
     */
    public function atualizar(Empresa $empresa, Unidade $unidade, array $flags = [], array $extras = []): array
    {
        $config = $this->configOuFalha($empresa, $unidade);

        if (empty($config->focus_empresa_id)) {
            throw new RuntimeException(
                'Unidade ainda não foi criada na Focus. Use criar() primeiro.'
            );
        }

        $payload = $this->montarPayload($empresa, $unidade, $flags);

        // Campos não-flag (ex: csc_nfce_*, id_token_nfce_*, discrimina_impostos)
        foreach ($extras as $k => $v) {
            if ($v !== null && $v !== '') {
                $payload[$k] = $v;
            }
        }

        $response = $this->master->put("/v2/empresas/{$config->focus_empresa_id}", $payload);

        if ($response->failed()) {
            $this->handleError($response, 'atualizar empresa');
        }

        $data = $response->json() ?? [];

        // Atualiza flags locais (sem mexer em tokens já existentes, exceto se Focus devolver novos)
        $updates = ['focus_sincronizado_em' => now()];
        if (! empty($data['token_producao'])) {
            $updates['focus_token_producao'] = $data['token_producao'];
        }
        if (! empty($data['token_homologacao'])) {
            $updates['focus_token_homologacao'] = $data['token_homologacao'];
        }
        $this->aplicarFlagsLocais($updates, $flags);
        $config->update($updates);

        Log::info('[FocusEmpresa] empresa atualizada', [
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'focus_empresa_id' => $config->focus_empresa_id,
        ]);

        return $data;
    }

    /**
     * Consulta uma empresa específica na Focus.
     *
     * @return array<string, mixed>
     */
    public function consultar(int $focusEmpresaId): array
    {
        $response = $this->master->get("/v2/empresas/{$focusEmpresaId}");

        if ($response->failed()) {
            $this->handleError($response, 'consultar empresa');
        }

        return $response->json() ?? [];
    }

    /**
     * Lista todas as empresas-filhas (endpoint de revenda — apenas super-admin).
     * Enriquece cada item com `ja_importada` (bool) e `empresa_local_id` (int|null)
     * consultando nossa tabela `configuracoes_fiscais` pelo `focus_empresa_id`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(): array
    {
        $response = $this->master->get('/v2/empresas');

        if ($response->failed()) {
            $this->handleError($response, 'listar empresas');
        }

        $raw = $response->json() ?? [];

        // Mapeia focus_empresa_id → empresa_id local já existente
        $idsFocus = array_column($raw, 'id');
        $existentes = [];
        if (! empty($idsFocus)) {
            $existentes = ConfiguracaoFiscal::withoutGlobalScopes()
                ->whereIn('focus_empresa_id', $idsFocus)
                ->pluck('empresa_id', 'focus_empresa_id')
                ->toArray();
        }

        return array_map(function ($item) use ($existentes) {
            $focusId = $item['id'] ?? null;
            return array_merge($item, [
                'ja_importada' => $focusId && isset($existentes[$focusId]),
                'empresa_local_id' => $focusId ? ($existentes[$focusId] ?? null) : null,
            ]);
        }, $raw);
    }

    // ─── Webhooks ──────────────────────────────────────────────────────

    /**
     * Eventos webhook que a Focus aceita em POST /v2/hooks.
     * Cada chamada do endpoint registra UM evento — não é array.
     */
    public const EVENTOS_SUPORTADOS = [
        'nfe',
        'nfse',
        'nfce_contingencia',
        'nfe_recebida',
        'nfse_recebida',
        'inutilizacao',
        'cte',
        'mdfe',
    ];

    /**
     * Cadastra UM webhook para um evento específico da empresa-filha.
     * A Focus exige 1 chamada POST /v2/hooks por evento — não aceita array.
     *
     * @return array<string, mixed> resposta da Focus incluindo `id` do hook
     */
    public function cadastrarWebhook(ConfiguracaoFiscal $config, string $url, string $evento): array
    {
        if (! in_array($evento, self::EVENTOS_SUPORTADOS, true)) {
            throw new \InvalidArgumentException(
                "Evento '{$evento}' não suportado. Eventos válidos: "
                . implode(', ', self::EVENTOS_SUPORTADOS)
            );
        }

        // Gera webhook_secret se ainda não tiver
        if (empty($config->webhook_secret)) {
            $config->webhook_secret = Str::random(48);
            $config->save();
        }

        $payload = [
            'cnpj' => $this->cnpjLimpo($config->unidade->cnpj ?? $config->empresa->cnpj),
            'event' => $evento,
            'url' => $url,
            'authorization' => 'Bearer ' . $config->webhook_secret,
        ];

        $response = $this->master->post('/v2/hooks', $payload);

        if ($response->failed()) {
            $this->handleError($response, "cadastrar webhook {$evento}");
        }

        $data = $response->json() ?? [];

        Log::info('[FocusEmpresa] webhook cadastrado', [
            'empresa_id' => $config->empresa_id,
            'unidade_id' => $config->unidade_id,
            'evento' => $evento,
            'webhook_id' => $data['id'] ?? null,
        ]);

        return $data;
    }

    /**
     * Sincroniza todos os webhooks aplicáveis para uma unidade.
     * Idempotente: remove os hooks órfãos antes de cadastrar.
     *
     * @param  array<int, string>|null  $eventos  evento por evento; null = todos os aplicáveis ao perfil da unidade
     * @return array<string, int>  evento => hook_id
     */
    public function sincronizarWebhooks(ConfiguracaoFiscal $config, ?array $eventos = null): array
    {
        $base = rtrim(config('services.focus_nfe.webhook_base_url') ?: config('app.url'), '/');
        $url = $base . '/webhooks/focusnfe';

        $eventos ??= $this->eventosAplicaveis($config);

        // Remove os webhooks já cadastrados para essa empresa (estado limpo)
        $cnpj = $this->cnpjLimpo($config->unidade->cnpj ?? $config->empresa->cnpj);
        $existentes = $this->listarWebhooks($cnpj);
        foreach ($existentes as $hook) {
            if (! empty($hook['id'])) {
                try {
                    $this->removerWebhook((string) $hook['id']);
                } catch (\Throwable $e) {
                    Log::warning('[FocusEmpresa] falha ao remover webhook órfão', [
                        'hook_id' => $hook['id'],
                        'erro' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Cadastra um por um
        $mapa = [];
        foreach ($eventos as $evento) {
            try {
                $data = $this->cadastrarWebhook($config, $url, $evento);
                // ID do hook é STRING alfanumérica na Focus (ex: "Y5P0na15"),
                // não inteiro — não fazer cast int.
                if (! empty($data['id'])) {
                    $mapa[$evento] = (string) $data['id'];
                }
            } catch (\Throwable $e) {
                Log::error('[FocusEmpresa] falha cadastrando webhook', [
                    'evento' => $evento,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        // Persiste mapa de hook IDs
        $config->forceFill(['focus_webhook_ids' => $mapa])->save();

        return $mapa;
    }

    /**
     * Decide quais eventos cadastrar para uma unidade conforme as flags da
     * ConfiguracaoFiscal (emite_nfe, emite_nfce, emite_nfse, etc).
     *
     * @return array<int, string>
     */
    public function eventosAplicaveis(ConfiguracaoFiscal $config): array
    {
        $eventos = [];

        if ($config->emite_nfe) {
            $eventos[] = 'nfe';
            $eventos[] = 'nfe_recebida';
        }
        if ($config->emite_nfce) {
            $eventos[] = 'nfce_contingencia';
            $eventos[] = 'inutilizacao';
        }
        if ($config->emite_nfse) {
            $eventos[] = 'nfse';
            $eventos[] = 'nfse_recebida';
        }

        return array_values(array_unique($eventos));
    }

    /**
     * Remove webhook por id (string alfanumérico da Focus, ex: "Y5P0na15").
     */
    public function removerWebhook(string $hookId): void
    {
        $response = $this->master->delete("/v2/hooks/{$hookId}");

        if ($response->failed() && $response->status() !== 404) {
            $this->handleError($response, 'remover webhook');
        }
    }

    /**
     * Lista webhooks da conta (todos as empresas-filhas).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarWebhooks(?string $cnpj = null): array
    {
        $query = $cnpj ? ['cnpj' => $this->cnpjLimpo($cnpj)] : [];
        $response = $this->master->get('/v2/hooks', $query);

        if ($response->failed()) {
            $this->handleError($response, 'listar webhooks');
        }

        return $response->json() ?? [];
    }

    // ─── Helpers privados ──────────────────────────────────────────────

    /**
     * Monta payload padrão para POST/PUT /v2/empresas.
     *
     * @param  array<string, bool>  $flags
     * @return array<string, mixed>
     */
    private function montarPayload(Empresa $empresa, Unidade $unidade, array $flags): array
    {
        // CNPJ da unidade se tiver, senão cai no CNPJ da empresa
        $cnpj = $this->cnpjLimpo($unidade->cnpj ?: $empresa->cnpj);

        $payload = [
            'nome'                 => $empresa->razao_social,
            'nome_fantasia'        => $unidade->nome ?: ($empresa->nome_fantasia ?: $empresa->razao_social),
            'cnpj'                 => $cnpj,
            'inscricao_estadual'   => $this->somenteDigitos($unidade->ie ?: $empresa->ie),
            'inscricao_municipal'  => $unidade->im ?: $empresa->im,
            'regime_tributario'    => $this->mapearRegime($empresa->regime_tributario?->value ?? 'simples'),
            'email'                => $empresa->email,
            'telefone'             => $this->somenteDigitos($unidade->telefone ?: $empresa->telefone),
            'cep'                  => $this->somenteDigitos($unidade->cep ?: $empresa->cep),
            'bairro'               => $unidade->bairro ?: $empresa->bairro,
            'municipio'            => $unidade->cidade ?: $empresa->cidade,
            'uf'                   => strtoupper($unidade->uf ?: $empresa->uf),
            'logradouro'           => $unidade->logradouro ?: $empresa->logradouro,
            'numero'               => $unidade->numero ?: $empresa->numero,
            'complemento'          => $unidade->complemento ?: $empresa->complemento,
            'discrimina_impostos'  => true,
        ];

        // Flags de habilitação (só enviamos se vieram explicitamente no array)
        $habilitaveis = [
            'habilita_nfe',
            'habilita_nfce',
            'habilita_nfse',
            'habilita_manifestacao',
            'habilita_contingencia_offline_nfce',
            'habilita_cte',
        ];
        foreach ($habilitaveis as $flag) {
            if (array_key_exists($flag, $flags)) {
                $payload[$flag] = (bool) $flags[$flag];
            }
        }

        return array_filter($payload, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Persiste os dados retornados pela Focus em configuracoes_fiscais.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, bool>  $flags
     */
    private function persistirNaConfiguracao(
        Empresa $empresa,
        Unidade $unidade,
        array $data,
        array $flags,
    ): void {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->where('unidade_id', $unidade->id)
            ->first();

        $payload = [
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'focus_empresa_id' => $data['id'] ?? null,
            'focus_token_producao' => $data['token_producao'] ?? null,
            'focus_token_homologacao' => $data['token_homologacao'] ?? null,
            'ambiente' => 'homologacao', // começa sempre em homologação
            'emissao_fiscal_ativa' => true,
            'focus_sincronizado_em' => now(),
        ];

        // Gera webhook_secret único para esta unidade
        if (! $config || empty($config->webhook_secret)) {
            $payload['webhook_secret'] = Str::random(48);
        }

        $this->aplicarFlagsLocais($payload, $flags);

        if ($config) {
            $config->update($payload);
        } else {
            ConfiguracaoFiscal::create($payload);
        }
    }

    /**
     * Reflete as flags enviadas à Focus nas colunas locais emite_*.
     *
     * @param  array<string, mixed>  $payload  Passado por referência, será mutado.
     * @param  array<string, bool>  $flags
     */
    private function aplicarFlagsLocais(array &$payload, array $flags): void
    {
        $map = [
            'habilita_nfe' => 'emite_nfe',
            'habilita_nfce' => 'emite_nfce',
            'habilita_nfse' => 'emite_nfse',
        ];
        foreach ($map as $apiFlag => $dbCol) {
            if (array_key_exists($apiFlag, $flags)) {
                $payload[$dbCol] = (bool) $flags[$apiFlag];
            }
        }
    }

    private function configOuFalha(Empresa $empresa, Unidade $unidade): ConfiguracaoFiscal
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->where('unidade_id', $unidade->id)
            ->first();

        if (! $config) {
            throw new RuntimeException(
                "Configuração fiscal não encontrada para unidade {$unidade->nome} (ID: {$unidade->id})."
            );
        }

        return $config;
    }

    /**
     * Converte o regime tributário do ERP para o código aceito pela Focus:
     *   1 = Simples Nacional
     *   2 = Simples Nacional (excesso de sublimite)
     *   3 = Regime Normal (Lucro Presumido ou Real)
     */
    private function mapearRegime(string $regime): int
    {
        return match (strtolower($regime)) {
            'simples', 'simples_nacional' => 1,
            'simples_excesso' => 2,
            'presumido', 'real', 'lucro_presumido', 'lucro_real', 'normal' => 3,
            default => 1,
        };
    }

    private function cnpjLimpo(?string $cnpj): string
    {
        return preg_replace('/\D+/', '', (string) $cnpj) ?? '';
    }

    private function somenteDigitos(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        return preg_replace('/\D+/', '', $valor) ?: null;
    }

    /**
     * Traduz erros da Focus para pt-BR amigável.
     * Focus devolve 400/422 com body {codigo, mensagem, erros?} em maioria dos endpoints.
     */
    private function handleError(\Illuminate\Http\Client\Response $response, string $contexto): never
    {
        $status = $response->status();
        $body = $response->json() ?? [];

        $codigo = $body['codigo'] ?? $body['erro'] ?? null;
        $mensagem = $body['mensagem'] ?? $body['message'] ?? $response->body();

        // Mensagens conhecidas → tradução amigável
        $amigavel = $this->traduzirErro((string) $codigo, (string) $mensagem);

        Log::error('[FocusEmpresa] falha ao ' . $contexto, [
            'status' => $status,
            'codigo' => $codigo,
            'mensagem' => $mensagem,
            'body' => $body,
        ]);

        throw new RuntimeException(
            "Focus NFe recusou ao {$contexto} (HTTP {$status}): {$amigavel}"
        );
    }

    private function traduzirErro(string $codigo, string $mensagem): string
    {
        $texto = strtolower($codigo . ' ' . $mensagem);

        return match (true) {
            str_contains($texto, 'certificado')
                && (str_contains($texto, 'senha') || str_contains($texto, 'password'))
                => 'Senha do certificado digital está incorreta. Reenvie o certificado com a senha correta.',

            str_contains($texto, 'certificado') && str_contains($texto, 'vencido')
                => 'O certificado digital enviado está vencido. Gere um novo certificado A1 e reenvie.',

            str_contains($texto, 'cnpj') && (str_contains($texto, 'divergente') || str_contains($texto, 'diferente'))
                => 'O CNPJ do certificado é diferente do CNPJ da empresa cadastrada.',

            str_contains($texto, 'cnpj') && str_contains($texto, 'já existe')
                => 'Este CNPJ já está cadastrado na Focus NFe. Use consultar() para obter os dados existentes.',

            str_contains($texto, 'cnpj') && str_contains($texto, 'inválido')
                => 'CNPJ informado é inválido. Verifique se foi digitado corretamente.',

            str_contains($texto, 'inscricao_estadual') || str_contains($texto, 'inscrição estadual')
                => 'Inscrição Estadual inválida ou obrigatória para o regime tributário informado.',

            str_contains($texto, 'token') && str_contains($texto, 'inválido')
                => 'Token master inválido. Verifique FOCUS_MASTER_TOKEN no .env.',

            str_contains($texto, 'plano')
                => 'Plano da conta Focus NFe não permite esta operação. Verifique sua contratação.',

            str_contains($texto, 'unauthorized') || str_contains($texto, '401')
                => 'Credenciais inválidas. Verifique o token master no .env.',

            default => $mensagem ?: 'Erro não especificado.',
        };
    }
}
