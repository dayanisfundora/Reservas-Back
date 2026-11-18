<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Installed copies only run the seeders on their first start, so permissions added later
 * must reach existing databases through a migration.
 */
return new class extends Migration
{
    private const GUARD = 'sanctum';

    private const PERMISSION = 'respaldar_bd';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => self::GUARD]);

        $admin = Role::query()->where('name', 'admin')->where('guard_name', self::GUARD)->first();
        if ($admin !== null && ! $admin->hasPermissionTo($permission)) {
            $admin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('name', self::PERMISSION)
            ->where('guard_name', self::GUARD)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
