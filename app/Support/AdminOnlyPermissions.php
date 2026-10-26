<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Permissions that only the admin role may hold.
 *
 * User, role and permission management stays exclusive to Admin.
 */
final class AdminOnlyPermissions
{
    public const NAMES = [
        'ver_usuarios',
        'crear_usuarios',
        'editar_usuarios',
        'eliminar_usuarios',
        'ver_roles',
        'crear_roles',
        'editar_roles',
        'eliminar_roles',
        'ver_permisos',
        'asignar_permisos',
    ];

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    public static function withoutAdminOnly(array $permissions): array
    {
        return array_values(array_diff($permissions, self::NAMES));
    }
}
