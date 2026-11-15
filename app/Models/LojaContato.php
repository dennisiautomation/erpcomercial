<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Contato captado pela loja online (popup, bloco de novidades), com o aceite — 14/11/2026. */
class LojaContato extends Model
{
    protected $table = 'loja_contatos';

    protected $fillable = ['empresa_id', 'nome', 'email', 'whatsapp', 'origem', 'aceitou_em'];

    protected function casts(): array
    {
        return ['aceitou_em' => 'datetime'];
    }
}
