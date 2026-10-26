<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\CurrentEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $empresaId = CurrentEmpresa::id();

        if ($empresaId !== null) {
            $builder->where($model->getTable() . '.empresa_id', $empresaId);
        }
    }
}
