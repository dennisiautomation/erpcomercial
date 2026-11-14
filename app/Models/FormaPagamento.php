<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

/**
 * Botão de forma de pagamento da empresa (14/11/2026).
 *
 * `chave`  — o que o botão envia. Nas do sistema é o próprio código de sempre
 *            ('dinheiro', 'pix', 'credishop'...); nas criadas é 'fp_xxxxxxxx'.
 * `tipo`   — como se comporta. A venda grava em `pagamento_detalhes.forma` o
 *            código gravado (ver codigoGravado()), então nota, caixa, tabela de
 *            preço, juros e relatórios seguem funcionando sem saber da forma nova.
 */
class FormaPagamento extends Model
{
    use BelongsToEmpresa;

    protected $table = 'formas_pagamento';

    protected $fillable = [
        'empresa_id', 'chave', 'nome', 'tipo', 'sistema',
        'icone', 'cor', 'largura', 'ordem', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'sistema' => 'boolean',
            'ativo'   => 'boolean',
            'largura' => 'integer',
            'ordem'   => 'integer',
        ];
    }

    /** Código que vai para pagamento_detalhes.forma / movimentacoes_caixa.forma_pagamento. */
    public function codigoGravado(): string
    {
        return $this->sistema ? $this->chave : $this->tipo;
    }
}
