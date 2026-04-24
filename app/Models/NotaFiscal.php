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

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

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
