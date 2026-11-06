<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Casa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MovementScopeFilter
{
    /** @param Builder<Model> $query */
    public static function apply(Builder $query, ?int $casaId = null): Builder
    {
        if ($casaId !== null) {
            $query->where($query->getModel()->getTable() . '.casa_id', $casaId);
        }

        return $query;
    }

    public static function resolveFromRequest(?string $casaId): array
    {
        $resolvedCasaId = ($casaId !== null && $casaId !== '' && ctype_digit($casaId)) ? (int) $casaId : null;

        return [$resolvedCasaId];
    }

    public static function validateCasaBelongsToEmpresa(int $casaId, int $empresaId): bool
    {
        return Casa::query()->whereKey($casaId)->where('empresa_id', $empresaId)->where('activo', true)->exists();
    }
}
