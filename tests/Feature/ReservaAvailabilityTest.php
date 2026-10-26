<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Casa;
use App\Models\Empresa;
use App\Models\Habitacion;
use App\Models\Reserva;
use App\Models\User;
use App\Support\CurrentEmpresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReservaAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private User $user;
    private Casa $casa;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['gestionar_reservas', 'ver_reservas', 'gestionar_habitaciones', 'ver_habitaciones', 'gestionar_casas'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $admin->syncPermissions(Permission::all());

        $this->empresa = Empresa::query()->create(['nombre' => 'Hostal Test', 'activo' => true]);
        $this->user = User::query()->create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local', 'password' => Hash::make('password'), 'is_active' => true]);
        $this->user->assignRole('admin');
        $this->user->empresas()->syncWithoutDetaching([$this->empresa->id]);
        Sanctum::actingAs($this->user);
        CurrentEmpresa::set($this->empresa->id);

        $this->casa = Casa::query()->create(['empresa_id' => $this->empresa->id, 'nombre' => 'Casa Test', 'activo' => true]);
    }

    #[Test]
    public function private_room_rejects_overlapping_reservation(): void
    {
        $room = $this->room(Habitacion::TIPO_PRIVADA, 2);

        $this->postReserva($room, ['cliente_nombre' => 'Ana', 'generos' => [Reserva::GENERO_FEMENINO]])->assertCreated();

        $this->postReserva($room, ['cliente_nombre' => 'Luis', 'fecha_entrada' => '2026-10-02', 'generos' => [Reserva::GENERO_MASCULINO]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['disponibilidad']);
    }

    #[Test]
    public function shared_room_rejects_mixed_genders(): void
    {
        $room = $this->room(Habitacion::TIPO_COMPARTIDA, 4);

        $this->postReserva($room, ['cliente_nombre' => 'Ana', 'genero' => Reserva::GENERO_FEMENINO])->assertCreated();

        $this->postReserva($room, ['cliente_nombre' => 'Luis', 'genero' => Reserva::GENERO_MASCULINO])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['disponibilidad']);
    }

    #[Test]
    public function confirming_pending_sets_confirmada(): void
    {
        $room = $this->room(Habitacion::TIPO_PRIVADA, 2);
        $id = $this->postReserva($room, ['cliente_nombre' => 'Ana', 'generos' => [Reserva::GENERO_FEMENINO]])->json('data.id');

        $this->withEmpresaHeader()->patchJson("/api/reservas/{$id}/confirmar")
            ->assertOk()
            ->assertJsonPath('data.estado', Reserva::ESTADO_CONFIRMADA);
    }

    #[Test]
    public function calendario_pdf_downloads(): void
    {
        $room = $this->room(Habitacion::TIPO_PRIVADA, 2);
        $this->postReserva($room, ['cliente_nombre' => 'Ana', 'generos' => [Reserva::GENERO_FEMENINO]])->assertCreated();

        $this->withEmpresaHeader()->getJson('/api/reservas/calendario/pdf?casa_id=' . $this->casa->id . '&desde=2026-10-01&hasta=2026-10-31')
            ->assertOk();
    }

    private function room(string $tipo, int $capacidad): Habitacion
    {
        return Habitacion::query()->create([
            'empresa_id' => $this->empresa->id,
            'casa_id' => $this->casa->id,
            'nombre' => $tipo . ' test',
            'tipo' => $tipo,
            'capacidad' => $capacidad,
            'activo' => true,
        ]);
    }

    private function postReserva(Habitacion $room, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $payload = [
            'habitacion_id' => $room->id,
            'cliente_nombre' => 'Cliente',
            'genero' => $room->isCompartida() ? Reserva::GENERO_FEMENINO : null,
            'generos' => $room->isPrivada() ? [Reserva::GENERO_FEMENINO] : null,
            'cantidad_personas' => 1,
            'camas' => ['matrimonial' => 0, 'twin' => 0, 'single' => 1],
            'cantidad_noches' => 3,
            'fecha_entrada' => '2026-10-01',
        ];

        return $this->withEmpresaHeader()->postJson('/api/reservas', array_merge($payload, $overrides));
    }

    private function withEmpresaHeader(): self
    {
        return $this->withHeader('X-Empresa-Id', (string) $this->empresa->id);
    }
}
