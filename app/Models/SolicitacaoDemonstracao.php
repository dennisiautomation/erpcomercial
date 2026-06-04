<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lead de demonstração vindo da landing pública.
 * Sem trait multi-tenant: é contato pré-venda, não pertence a uma empresa.
 */
class SolicitacaoDemonstracao extends Model
{
    protected $table = 'solicitacoes_demonstracao';

    protected $fillable = [
        'nome',
        'empresa',
        'email',
        'whatsapp',
        'qtd_lojas',
        'status',
        'origem',
        'ip',
        'observacao',
    ];

    public const STATUS_LABELS = [
        'novo'       => 'Novo',
        'contatado'  => 'Contatado',
        'convertido' => 'Convertido',
        'descartado' => 'Descartado',
    ];
}
