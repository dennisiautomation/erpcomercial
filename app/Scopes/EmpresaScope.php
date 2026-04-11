<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class EmpresaScope implements Scope
{
    private static bool $applying = false;

    public function apply(Builder $builder, Model $model): void
    {
        // Prevent infinite recursion: auth()->user() loads User which has this scope
        if (self::$applying) {
            return;
        }

        self::$applying = true;

        try {
            if (auth()->check() && auth()->user()->empresa_id) {
                $builder->where($model->getTable() . '.empresa_id', auth()->user()->empresa_id);
            }
        } finally {
            self::$applying = false;
        }
    }
}
