<?php

namespace App\Models;

use App\Enums\TipoMovimentacaoCaixa;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentacaoCaixa extends Model
{
    use HasFactory, BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'movimentacoes_caixa';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'caixa_id',
        'tipo',
        'valor',
        'descricao',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentacaoCaixa::class,
            'valor' => 'decimal:2',
        ];
    }

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
