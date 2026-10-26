<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->empresa = Empresa::query()->create(['nombre' => 'Hostal Test', 'activo' => true]);
        $this->admin = $this->makeUser('admin', 'admin');
        Sanctum::actingAs($this->admin);
    }

    #[Test]
    public function admin_creates_user_with_roles_and_empresas_and_user_can_log_in(): void
    {
        $this->postJson('/api/users', [
            'name' => 'Recepción Uno',
            'username' => 'recepcion1',
            'email' => 'recepcion1@test.local',
            'password' => 'secreto123',
            'roles' => ['recepcionista'],
            'empresa_ids' => [$this->empresa->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.roles.0.name', 'recepcionista')
            ->assertJsonPath('data.empresas.0.id', $this->empresa->id)
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/auth/login', ['name' => 'recepcion1', 'password' => 'secreto123'])
            ->assertOk()
            ->assertJsonPath('data.empresas.0.id', $this->empresa->id);
    }

    #[Test]
    public function create_user_validates_unique_username(): void
    {
        $this->postJson('/api/users', [
            'name' => 'Otro',
            'username' => 'admin',
            'email' => 'otro@test.local',
            'password' => 'secreto123',
        ])->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    #[Test]
    public function admin_updates_user_and_deactivation_revokes_tokens(): void
    {
        $user = $this->makeUser('recepcion2', 'recepcionista');
        $user->createToken('api-token');

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Recepción Editada',
            'is_active' => false,
            'roles' => ['auditor'],
            'empresa_ids' => [],
            'password' => '',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Recepción Editada')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.roles.0.name', 'auditor')
            ->assertJsonCount(0, 'data.empresas');

        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    #[Test]
    public function admin_cannot_deactivate_delete_or_demote_itself(): void
    {
        $this->putJson("/api/users/{$this->admin->id}", ['is_active' => false])->assertStatus(422);
        $this->postJson("/api/users/{$this->admin->id}/assign-roles", ['roles' => ['auditor']])->assertStatus(422);
        $this->deleteJson("/api/users/{$this->admin->id}")->assertForbidden();
    }

    #[Test]
    public function admin_deletes_other_user(): void
    {
        $user = $this->makeUser('borrar', 'auditor');

        $this->deleteJson("/api/users/{$user->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function roles_endpoint_manages_roles_without_admin_only_permissions(): void
    {
        $roleId = $this->postJson('/api/roles', ['name' => 'limpieza', 'permissions' => ['ver_reservas', 'ver_usuarios']])
            ->assertCreated()
            ->json('data.id');

        $this->getJson('/api/roles')->assertOk()->assertJsonFragment(['name' => 'limpieza']);
        $this->assertSame(['ver_reservas'], \Spatie\Permission\Models\Role::findById($roleId, 'sanctum')->permissions->pluck('name')->all());

        $this->getJson('/api/permissions')->assertOk()->assertJsonMissing(['name' => 'ver_usuarios']);
        $this->deleteJson("/api/roles/{$roleId}")->assertOk();
    }

    #[Test]
    public function non_admin_cannot_manage_users(): void
    {
        Sanctum::actingAs($this->makeUser('recepcion3', 'recepcionista'));

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [])->assertForbidden();
        $this->getJson('/api/roles')->assertForbidden();
    }

    private function makeUser(string $username, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($username),
            'username' => $username,
            'email' => "{$username}@test.local",
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $user->empresas()->sync([$this->empresa->id]);

        return $user;
    }
}
