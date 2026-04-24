<?php

namespace App\Models;

use App\Enums\TipoManifestacao;
use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NFeRecebida extends Model
{
    use BelongsToEmpresa, BelongsToUnidade, SoftDeletes, AuditableModel;

    protected $table = 'nfes_recebidas';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'chave_acesso',
        'cnpj_emitente',
        'nome_emitente',
        'numero',
        'serie',
        'valor_total',
        'data_emissao',
        'tipo_ultima_manifestacao',
        'protocolo_manifestacao',
        'manifestada_em',
        'manifestada_por',
        'xml_url',
        'danfe_url',
        'sincronizada_em',
    ];

    protected $auditFields = ['tipo_ultima_manifestacao', 'protocolo_manifestacao', 'manifestada_em', 'manifestada_por'];

    protected function casts(): array
    {
        return [
            'valor_total'             => 'decimal:2',
            'data_emissao'            => 'date',
            'manifestada_em'          => 'datetime',
            'sincronizada_em'         => 'datetime',
            'tipo_ultima_manifestacao' => TipoManifestacao::class,
        ];
    }

    public function manifestador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manifestada_por');
    }

    public function foiManifestada(): bool
    {
        return $this->tipo_ultima_manifestacao !== null;
    }
}
