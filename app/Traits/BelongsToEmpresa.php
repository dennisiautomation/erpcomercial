<?php

namespace App\Traits;

use App\Models\Empresa;
use App\Scopes\EmpresaScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToEmpresa
{
    protected static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope(new EmpresaScope);

        static::creating(function ($model) {
            if (! $model->empresa_id && auth()->check()) {
                $model->empresa_id = auth()->user()->empresa_id;
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
