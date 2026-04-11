<?php

namespace App\Traits;

use App\Models\Unidade;
use App\Scopes\UnidadeScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUnidade
{
    protected static function bootBelongsToUnidade(): void
    {
        static::addGlobalScope(new UnidadeScope);

        static::creating(function ($model) {
            if (! $model->unidade_id && session()->has('unidade_id')) {
                $model->unidade_id = session('unidade_id');
            }
        });
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}
