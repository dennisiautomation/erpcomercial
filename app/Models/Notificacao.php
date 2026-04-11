<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Notificacao extends Model
{
    use BelongsToEmpresa;

    protected $table = 'notificacoes';

    protected $fillable = [
        'user_id',
        'empresa_id',
        'tipo',
        'titulo',
        'mensagem',
        'url',
        'icone',
        'cor',
        'lida',
        'lida_em',
    ];

    protected $casts = [
        'lida' => 'boolean',
        'lida_em' => 'datetime',
    ];

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeNaoLidas(Builder $query): Builder
    {
        return $query->where('lida', false);
    }

    public function scopeRecentes(Builder $query, int $limit = 5): Builder
    {
        return $query->latest()->limit($limit);
    }
}
