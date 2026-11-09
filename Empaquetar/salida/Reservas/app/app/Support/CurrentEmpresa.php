<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Empresa;

class CurrentEmpresa
{
    private const CONTAINER_KEY = 'currentEmpresaId';

    public static function set(?int $empresaId): void
    {
        if ($empresaId === null) {
            app()->forgetInstance(self::CONTAINER_KEY);
            return;
        }

        app()->instance(self::CONTAINER_KEY, $empresaId);
    }

    public static function id(): ?int
    {
        if (! app()->bound(self::CONTAINER_KEY)) {
            return null;
        }

        $empresaId = app(self::CONTAINER_KEY);

        return is_int($empresaId) ? $empresaId : null;
    }

    public static function current(): ?Empresa
    {
        $empresaId = self::id();

        return $empresaId === null ? null : Empresa::query()->find($empresaId);
    }
}
