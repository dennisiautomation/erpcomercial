<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comissao extends Model
{
    use HasFactory, SoftDeletes, BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'comissoes';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'user_id',
        'venda_id',
        'valor_venda',
        'percentual',
        'valor_comissao',
        'status',
        'pago_em',
    ];

    protected function casts(): array
    {
        return [
            'valor_venda' => 'decimal:2',
            'percentual' => 'decimal:2',
            'valor_comissao' => 'decimal:2',
            'pago_em' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }
}
