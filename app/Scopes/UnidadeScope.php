<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UnidadeScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (session()->has('unidade_id')) {
            $user = auth()->user();

            // Admin e Dono veem todas as unidades — scope só aplica para perfis de unidade
            $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;
            if ($user && ! in_array($perfil, ['admin', 'dono'])) {
                $builder->where($model->getTable() . '.unidade_id', session('unidade_id'));
            }
        }
    }
}
