<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrcamentoItem extends Model
{
    use HasFactory;

    protected $table = 'orcamento_itens';

    protected $fillable = [
        'orcamento_id',
        'produto_id',
        'servico_id',
        'descricao',
        'quantidade',
        'preco_unitario',
        'desconto_percentual',
        'desconto_valor',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario' => 'decimal:2',
            'desconto_valor' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
