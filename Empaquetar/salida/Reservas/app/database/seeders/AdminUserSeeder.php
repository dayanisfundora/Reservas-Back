<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Casa;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Initial data for production installs (desktop package): the admin user, an empresa and a casa
 * to complete, without demo habitaciones. Existing records are never modified.
 *
 * @example php artisan db:seed --class=AdminUserSeeder
 */
class AdminUserSeeder extends Seeder
{
    public const USERNAME = 'admin';

    public const EMAIL = 'admin@reservas.local';

    public const INITIAL_PASSWORD = 'Admin12345*';

    public const EMPRESA = 'Empresa principal';

    public const CASA = 'Casa principal';

    public function run(): void
    {
        $empresa = Empresa::query()->firstOrCreate(
            ['nombre' => self::EMPRESA],
            ['nombre_corto' => 'Principal', 'activo' => true]
        );

        $admin = User::query()->firstOrCreate(
            ['username' => self::USERNAME],
            [
                'name' => 'Administrador',
                'email' => self::EMAIL,
                'password' => self::INITIAL_PASSWORD,
                'is_active' => true,
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
        $admin->empresas()->syncWithoutDetaching([$empresa->id]);

        Casa::withoutGlobalScopes()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => self::CASA],
            ['codigo' => 'CASA-1', 'activo' => true]
        );

        $this->command?->info('Administrador inicial: ' . self::USERNAME . ' / ' . self::INITIAL_PASSWORD);
    }
}
