<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;

class ConfiguracaoFiscal extends Model
{
    use BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'configuracoes_fiscais';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'ambiente',
        'focus_token',
        'serie_nfe',
        'serie_nfce',
        'serie_nfse',
        'csc_nfce',
        'csc_id_nfce',
        'nfse_item_lista_servico',
        'nfse_codigo_tributacao',
        'nfse_regime_especial',
        'nfse_incentivador_cultural',
        'certificado_validade',
        'emissao_fiscal_ativa',
        'tipo_cupom_pdv',
        'emite_nfe',
        'emite_nfce',
        'emite_nfse',
    ];

    protected $hidden = [
        'focus_token',
    ];

    protected function casts(): array
    {
        return [
            'emissao_fiscal_ativa' => 'boolean',
            'emite_nfe' => 'boolean',
            'emite_nfce' => 'boolean',
            'emite_nfse' => 'boolean',
            'nfse_incentivador_cultural' => 'boolean',
            'certificado_validade' => 'date',
        ];
    }
}
