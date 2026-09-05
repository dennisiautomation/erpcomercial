<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gateway de pagamento por empresa (hoje: PIX Sicredi do Agente IA).
 *
 * SEM BelongsToEmpresa de propósito (mesma razão do AgenteIaConfig):
 * consultado pela API máquina-a-máquina e pelo webhook, fora de sessão
 * web. Todo acesso filtra empresa_id explicitamente.
 *
 * client_id/client_secret são cifrados com APP_KEY (cast encrypted) —
 * nunca aparecem em claro no banco nem em dumps.
 */
class EmpresaGateway extends Model
{
    protected $table = 'empresa_gateways';

    public const PROVEDOR_SICREDI_PIX = 'sicredi_pix';

    // Fase 3 (13/08/2026): entrega local via Uber Direct — client_id/secret da
    // conta Uber da EMPRESA (cada cliente tem a própria); customer_id + faixas
    // de CEP + janelas de horário vivem no `config` JSON.
    public const PROVEDOR_UBER_DIRECT = 'uber_direct';

    // Fase 2 (13/08/2026): Asaas p/ cartão (link) — api_key no client_secret.
    public const PROVEDOR_ASAAS = 'asaas';

    // 05/09/2026: Melhor Envio (frete para outra cidade no Agente IA) — a
    // EMPRESA autoriza a própria conta via OAuth do app IA365 (credenciais
    // do app em plataforma_configuracoes); tokens cifrados abaixo, pacote
    // padrão/serviços/seguro no `config` JSON.
    public const PROVEDOR_MELHOR_ENVIO = 'melhor_envio';

    protected $fillable = [
        'empresa_id',
        'provedor',
        'ativo',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expira_em',
        'chave_pix',
        'base_url',
        'cert_path',
        'key_path',
        'expiracao_segundos',
        'webhook_registrado_em',
        'ultima_falha',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expira_em' => 'datetime',
            'expiracao_segundos' => 'integer',
            'webhook_registrado_em' => 'datetime',
            'config' => 'array',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function sicrediAtivoPara(int $empresaId): ?self
    {
        return static::ativoPara($empresaId, self::PROVEDOR_SICREDI_PIX);
    }

    public static function ativoPara(int $empresaId, string $provedor): ?self
    {
        return static::where('empresa_id', $empresaId)
            ->where('provedor', $provedor)
            ->where('ativo', true)
            ->first();
    }

    /** Config completa para uso (cert/key presentes)? */
    public function utilizavel(): bool
    {
        return filled($this->client_id)
            && filled($this->client_secret)
            && filled($this->chave_pix)
            && filled($this->cert_path)
            && filled($this->key_path);
    }
}
