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

    protected $fillable = [
        'empresa_id',
        'provedor',
        'ativo',
        'client_id',
        'client_secret',
        'chave_pix',
        'base_url',
        'cert_path',
        'key_path',
        'expiracao_segundos',
        'webhook_registrado_em',
        'ultima_falha',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'expiracao_segundos' => 'integer',
            'webhook_registrado_em' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function sicrediAtivoPara(int $empresaId): ?self
    {
        return static::where('empresa_id', $empresaId)
            ->where('provedor', self::PROVEDOR_SICREDI_PIX)
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
