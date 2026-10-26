<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::query()->create(['nombre' => 'Hostal Test', 'activo' => true]);
        $this->user = User::query()->create([
            'name' => 'Recepcion',
            'username' => 'recepcion',
            'email' => 'recepcion@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->empresas()->syncWithoutDetaching([$this->empresa->id]);
    }

    #[Test]
    public function login_accepts_username_or_email_and_returns_empresas(): void
    {
        foreach (['recepcion', 'RECEPCION@test.local'] as $login) {
            $this->postJson('/api/auth/login', ['name' => $login, 'password' => 'password'])
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'username', 'email', 'roles'], 'permissions', 'empresas']])
                ->assertJsonPath('data.empresas.0.id', $this->empresa->id);
        }
    }

    #[Test]
    public function login_rejects_wrong_password(): void
    {
        $this->postJson('/api/auth/login', ['name' => 'recepcion', 'password' => 'incorrecta'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function login_rejects_inactive_user(): void
    {
        $this->user->update(['is_active' => false]);

        $this->postJson('/api/auth/login', ['name' => 'recepcion', 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function login_rejects_user_without_empresas(): void
    {
        $this->user->empresas()->detach();

        $this->postJson('/api/auth/login', ['name' => 'recepcion', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    #[Test]
    public function logout_revokes_current_token(): void
    {
        $token = $this->user->createToken('api-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.user.username', 'recepcion');
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function profile_updates_data_and_password_with_current_password(): void
    {
        $token = $this->user->createToken('api-token')->plainTextToken;

        $this->withToken($token)->putJson('/api/auth/profile', [
            'name' => 'Recepción Principal',
            'username' => 'recepcion_principal',
            'email' => 'principal@test.local',
            'current_password' => 'password',
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.username', 'recepcion_principal')
            ->assertJsonPath('data.user.email', 'principal@test.local');

        $this->assertTrue(Hash::check('nuevaClave123', $this->user->fresh()->password));
    }

    #[Test]
    public function profile_rejects_wrong_current_password(): void
    {
        $token = $this->user->createToken('api-token')->plainTextToken;

        $this->withToken($token)->putJson('/api/auth/profile', [
            'name' => 'Recepcion',
            'username' => 'recepcion',
            'email' => 'recepcion@test.local',
            'current_password' => 'incorrecta',
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('password', $this->user->fresh()->password));
    }

    #[Test]
    public function empresa_routes_require_header_and_access(): void
    {
        $token = $this->user->createToken('api-token')->plainTextToken;
        $otra = Empresa::query()->create(['nombre' => 'Otra', 'activo' => true]);

        $this->withToken($token)->getJson('/api/casas')->assertStatus(422);
        $this->withToken($token)->withHeader('X-Empresa-Id', (string) $otra->id)->getJson('/api/casas')->assertForbidden();
        $this->withToken($token)->withHeader('X-Empresa-Id', (string) $this->empresa->id)->getJson('/api/casas')->assertOk();
    }
}
