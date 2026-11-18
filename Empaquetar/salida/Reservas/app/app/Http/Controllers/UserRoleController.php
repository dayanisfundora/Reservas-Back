<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserRoleController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with(['roles', 'empresas'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->present($user))
            ->values();

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_active' => $validated['is_active'] ?? true,
            ]);
            $user->syncRoles($validated['roles'] ?? []);
            $user->empresas()->sync($validated['empresa_ids'] ?? []);

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado correctamente.',
            'data' => $this->present($user->fresh(['roles', 'empresas'])),
        ], 201);
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $validated = $request->validated();

        if ($request->user()->is($user)) {
            if (array_key_exists('is_active', $validated) && ! $validated['is_active']) {
                return $this->error('No puedes desactivar tu propio usuario.');
            }

            if (array_key_exists('roles', $validated) && $user->hasRole('admin') && ! in_array('admin', $validated['roles'], true)) {
                return $this->error('No puedes quitarte el rol admin a ti mismo.');
            }
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->fill(Arr::only($validated, ['name', 'username', 'email', 'is_active']));

            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
            }

            $user->save();

            if (array_key_exists('roles', $validated)) {
                $user->syncRoles($validated['roles']);
            }

            if (array_key_exists('empresa_ids', $validated)) {
                $user->empresas()->sync($validated['empresa_ids']);
            }

            if (! $user->is_active) {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente.',
            'data' => $this->present($user->fresh(['roles', 'empresas'])),
        ]);
    }

    public function assignRoles(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if ($request->user()->is($user) && $user->hasRole('admin') && ! in_array('admin', $validated['roles'], true)) {
            return $this->error('No puedes quitarte el rol admin a ti mismo.');
        }

        $user->syncRoles($validated['roles']);

        return response()->json([
            'success' => true,
            'message' => 'Roles actualizados correctamente.',
            'data' => $this->present($user->fresh(['roles', 'empresas'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if ($request->user()->is($user)) {
            return $this->error('No puedes eliminar tu propio usuario.', 403);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['success' => true, 'message' => 'Usuario eliminado correctamente.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'roles' => $user->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values(),
            'empresas' => $user->empresas->map(fn (Empresa $empresa) => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre,
                'nombre_corto' => $empresa->nombre_corto,
            ])->values(),
            'created_at' => $user->created_at?->toDateTimeString(),
        ];
    }

    private function error(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
