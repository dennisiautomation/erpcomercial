<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Chave de idempotência das escritas do app POS (ver migration create_pos_tables). */
class PosOperacao extends Model
{
    protected $table = 'pos_operacoes';

    protected $fillable = ['empresa_id', 'user_id', 'chave', 'tipo', 'status', 'venda_id', 'pedido_id'];
}
