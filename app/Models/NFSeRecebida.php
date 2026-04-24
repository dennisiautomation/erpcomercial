<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * NFS-e em que a empresa é tomadora (contratou o serviço).
 * Espelho do NFeRecebida mas para serviços.
 *
 * A identidade é composta por (cnpj_prestador, codigo_verificacao) porque
 * NFS-e não tem chave de acesso de 44 dígitos como a NF-e — cada portal
 * (prefeitura ou nacional) emite um código próprio.
 */
class NFSeRecebida extends Model
{
    use BelongsToEmpresa, BelongsToUnidade, SoftDeletes, AuditableModel;

    protected $table = 'nfses_recebidas';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'codigo_verificacao',
        'numero',
        'serie',
        'cnpj_prestador',
        'nome_prestador',
        'municipio_prestador',
        'discriminacao',
        'item_lista_servico',
        'codigo_servico',
        'padrao',
        'valor_servicos',
        'valor_iss',
        'aliquota_iss',
        'iss_retido',
        'data_emissao',
        'data_competencia',
        'status',
        'xml_url',
        'pdf_url',
        'sincronizada_em',
    ];

    protected function casts(): array
    {
        return [
            'valor_servicos'   => 'decimal:2',
            'valor_iss'        => 'decimal:2',
            'aliquota_iss'     => 'decimal:2',
            'iss_retido'       => 'boolean',
            'data_emissao'     => 'date',
            'data_competencia' => 'date',
            'sincronizada_em'  => 'datetime',
        ];
    }
}
