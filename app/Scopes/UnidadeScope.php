<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UnidadeScope implements Scope
{
    private static bool $applying = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$applying) {
            return;
        }

        if (session()->has('unidade_id')) {
            self::$applying = true;

            try {
                $user = auth()->user();
                $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;

                // Admin e Dono veem todas as unidades
                if ($user && ! in_array($perfil, ['admin', 'dono'])) {
                    $builder->where($model->getTable() . '.unidade_id', session('unidade_id'));
                }
            } finally {
                self::$applying = false;
            }
        }
    }
}
