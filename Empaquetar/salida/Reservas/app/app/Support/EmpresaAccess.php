<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EmpresaAccess
{
    /** @return Collection<int, Empresa> */
    public static function accessibleFor(User $user): Collection
    {
        if ($user->hasRole('admin')) {
            return Empresa::query()->where('activo', true)->orderBy('nombre')->get();
        }

        return $user->empresas()->where('activo', true)->orderBy('nombre')->get();
    }

    public static function userCanAccess(User $user, int $empresaId): bool
    {
        if ($user->hasRole('admin')) {
            return Empresa::query()->whereKey($empresaId)->where('activo', true)->exists();
        }

        return $user->empresas()->where('empresas.id', $empresaId)->where('activo', true)->exists();
    }
}
