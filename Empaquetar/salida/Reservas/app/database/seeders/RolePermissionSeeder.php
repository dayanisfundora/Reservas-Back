<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    private const GUARD = 'sanctum';

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'ver_reservas', 'gestionar_reservas',
            'ver_habitaciones', 'gestionar_habitaciones',
            'gestionar_casas', 'gestionar_empresas',
            'ver_usuarios', 'crear_usuarios', 'editar_usuarios', 'eliminar_usuarios',
            'ver_roles', 'crear_roles', 'editar_roles', 'eliminar_roles',
            'ver_permisos', 'asignar_permisos',
            'respaldar_bd',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => self::GUARD]);
        }

        $matrix = [
            'admin' => $permissions,
            'dueño' => ['ver_reservas', 'gestionar_reservas', 'ver_habitaciones', 'gestionar_habitaciones', 'gestionar_casas'],
            'recepcionista' => ['ver_reservas', 'gestionar_reservas', 'ver_habitaciones'],
            'auditor' => ['ver_reservas', 'ver_habitaciones'],
        ];

        foreach ($matrix as $role => $rolePermissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => self::GUARD])->syncPermissions($rolePermissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
