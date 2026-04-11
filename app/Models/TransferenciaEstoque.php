<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferenciaEstoque extends Model
{
    use BelongsToEmpresa, SoftDeletes;

    protected $table = 'transferencias_estoque';

    protected $fillable = [
        'empresa_id',
        'unidade_origem_id',
        'unidade_destino_id',
        'user_solicitante_id',
        'user_aprovador_id',
        'status',
        'observacoes',
    ];

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidadeOrigem(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_origem_id');
    }

    public function unidadeDestino(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_destino_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_solicitante_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_aprovador_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(TransferenciaEstoqueItem::class);
    }
}
