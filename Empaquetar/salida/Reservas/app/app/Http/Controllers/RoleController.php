<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\AdminOnlyPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private const GUARD = 'sanctum';

    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', self::GUARD)
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', self::GUARD)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::query()->create(['name' => $validated['name'], 'guard_name' => self::GUARD]);
        $role->syncPermissions(AdminOnlyPermissions::withoutAdminOnly($validated['permissions'] ?? []));
        $this->flushPermissionCache();

        return response()->json([
            'success' => true,
            'message' => 'Rol creado correctamente.',
            'data' => $role->load('permissions'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::query()->findOrFail($id);

        if ($role->name === 'admin') {
            return response()->json(['success' => false, 'message' => 'El rol admin no se puede modificar.'], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', self::GUARD)->ignore($role->id)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update(['name' => $validated['name']]);

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions(AdminOnlyPermissions::withoutAdminOnly($validated['permissions']));
        }

        $this->flushPermissionCache();

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado correctamente.',
            'data' => $role->load('permissions'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::query()->withCount('users')->findOrFail($id);

        if ($role->name === 'admin') {
            return response()->json(['success' => false, 'message' => 'El rol admin no se puede eliminar.'], 422);
        }

        if ($role->users_count > 0) {
            return response()->json(['success' => false, 'message' => 'No se puede eliminar un rol asignado a usuarios.'], 422);
        }

        $role->delete();
        $this->flushPermissionCache();

        return response()->json(['success' => true, 'message' => 'Rol eliminado correctamente.']);
    }

    private function flushPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
