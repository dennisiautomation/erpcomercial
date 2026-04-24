<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartaCorrecao extends Model
{
    use BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'cartas_correcao';

    protected $fillable = [
        'nota_fiscal_id',
        'empresa_id',
        'unidade_id',
        'user_id',
        'numero_sequencia',
        'correcao',
        'status',
        'protocolo',
        'mensagem_sefaz',
        'xml_url',
        'pdf_url',
        'enviada_em',
    ];

    protected function casts(): array
    {
        return [
            'enviada_em' => 'datetime',
        ];
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
