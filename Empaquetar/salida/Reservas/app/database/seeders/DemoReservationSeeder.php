<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Casa;
use App\Models\Empresa;
use App\Models\Habitacion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoReservationSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstOrCreate(
            ['nombre' => 'Hostal Demo'],
            ['nombre_corto' => 'Demo', 'nit' => 'DEMO-001', 'activo' => true]
        );

        $user = User::firstOrCreate(
            ['username' => 'admin'],
            ['name' => 'Administrador', 'email' => 'admin@reservas.test', 'password' => Hash::make('password'), 'is_active' => true]
        );
        $user->assignRole('admin');
        $user->empresas()->syncWithoutDetaching([$empresa->id]);

        $casa = Casa::withoutGlobalScopes()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => 'Casa Principal'],
            ['codigo' => 'CASA-1', 'descripcion' => 'Casa inicial para pruebas', 'activo' => true]
        );

        Habitacion::withoutGlobalScopes()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'casa_id' => $casa->id, 'nombre' => 'Privada 1'],
            ['tipo' => Habitacion::TIPO_PRIVADA, 'capacidad' => 2, 'color' => '#0f766e', 'activo' => true]
        );

        Habitacion::withoutGlobalScopes()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'casa_id' => $casa->id, 'nombre' => 'Compartida 1'],
            ['tipo' => Habitacion::TIPO_COMPARTIDA, 'capacidad' => 4, 'color' => '#1d4ed8', 'activo' => true]
        );
    }
}
