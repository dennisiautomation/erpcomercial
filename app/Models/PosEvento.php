<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Log de atividade do app POS — retenção mínima de 180 dias (contrato Stone). */
class PosEvento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pos_eventos';

    protected $fillable = [
        'empresa_id', 'user_id', 'unidade_id', 'dispositivo_serial',
        'tipo', 'mensagem', 'dados', 'ocorrido_em',
    ];

    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'ocorrido_em' => 'datetime',
        ];
    }
}
