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
        'csc_nfce',
        'csc_id_nfce',
        'certificado_validade',
        'emissao_fiscal_ativa',
        'tipo_cupom_pdv',
    ];

    protected $hidden = [
        'focus_token',
    ];

    protected function casts(): array
    {
        return [
            'emissao_fiscal_ativa' => 'boolean',
            'certificado_validade' => 'date',
        ];
    }
}
