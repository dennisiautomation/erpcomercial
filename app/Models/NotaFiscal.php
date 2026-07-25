<?php

namespace App\Models;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotaFiscal extends Model
{
    use BelongsToEmpresa, BelongsToUnidade, SoftDeletes, AuditableModel;

    protected $auditFields = ['status', 'numero', 'chave_acesso', 'focus_status', 'cancelamento_motivo', 'cancelamento_protocolo'];

    protected $table = 'notas_fiscais';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'tipo',
        'numero',
        'serie',
        'chave_acesso',
        'natureza_operacao',
        'venda_id',
        'cliente_id',
        'valor_total',
        'status',
        'focus_ref',
        'focus_status',
        'focus_mensagem',
        'xml_url',
        'danfe_url',
        'qrcode_url',
        'url_consulta',
        'protocolo',
        'pdf_url',
        'cancelamento_motivo',
        'cancelamento_protocolo',
        'ambiente',
        'emitida_em',
        'cancelada_em',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoNotaFiscal::class,
            'status' => StatusNotaFiscal::class,
            'valor_total' => 'decimal:2',
            'emitida_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // NF-e de faturamento de pedido autorizada (webhook ou polling):
        // envia XML + DANFE por e-mail ao cliente automaticamente.
        static::updated(function (NotaFiscal $nota) {
            if (! $nota->wasChanged('status')
                || $nota->status?->value !== 'autorizada'
                || $nota->tipo?->value !== 'nfe') {
                return;
            }

            $venda = $nota->venda()->withoutGlobalScopes()->first();
            if ($venda?->tipo !== 'pedido') {
                return;
            }

            $email = $venda->cliente()->withoutGlobalScopes()->value('email');
            if ($email) {
                \App\Jobs\EnviarEmailNotaFiscalJob::dispatch($nota->id, $email);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * A Focus devolve `caminho_danfe`/`caminho_xml` RELATIVOS ao host dela
     * (ex.: /notas_fiscais_consumidor/NFe...html). Redirecionar esse caminho
     * direto joga o usuário em erp.ia365.com.br/... → 404. Resolve para o
     * host da Focus conforme o ambiente da nota.
     */
    public function urlArquivoFocus(?string $caminho): ?string
    {
        if (! $caminho) {
            return null;
        }
        if (str_starts_with($caminho, 'http://') || str_starts_with($caminho, 'https://')) {
            return $caminho;
        }

        $base = ($this->ambiente ?? 'homologacao') === 'producao'
            ? 'https://api.focusnfe.com.br'
            : 'https://homologacao.focusnfe.com.br';

        return $base . '/' . ltrim($caminho, '/');
    }

    public function getDanfeUrlCompletaAttribute(): ?string
    {
        return $this->urlArquivoFocus($this->danfe_url ?? $this->pdf_url);
    }

    public function getXmlUrlCompletaAttribute(): ?string
    {
        return $this->urlArquivoFocus($this->xml_url);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cartasCorrecao(): HasMany
    {
        return $this->hasMany(CartaCorrecao::class)->orderByDesc('numero_sequencia');
    }

    /** Eventos avançados (Ator Interessado, Insucesso de Entrega, EPEC, etc). */
    public function eventos(): HasMany
    {
        return $this->hasMany(NFeEvento::class)->orderByDesc('created_at');
    }
}
