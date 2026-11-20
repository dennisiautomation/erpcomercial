<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pedido de teste grátis feito no site, aguardando a confirmação do e-mail.
 * Sem tenant: a empresa ainda não existe. Ver ContratacaoSite.
 */
class CadastroSite extends Model
{
    protected $table = 'cadastros_site';

    protected $fillable = [
        'token_hash', 'email', 'cnpj', 'plano_id', 'dados', 'ip',
        'expira_em', 'confirmado_em', 'empresa_id',
    ];

    protected function casts(): array
    {
        return [
            'dados'         => 'encrypted:array',
            'expira_em'     => 'datetime',
            'confirmado_em' => 'datetime',
        ];
    }

    public function plano()
    {
        return $this->belongsTo(Plano::class);
    }

    public function vencido(): bool
    {
        return $this->expira_em->isPast();
    }
}
