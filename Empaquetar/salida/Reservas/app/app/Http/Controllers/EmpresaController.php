<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Support\EmpresaAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresas = EmpresaAccess::accessibleFor($request->user())->map(fn (Empresa $e) => $e->toSummaryArray())->values();
        return response()->json(['success' => true, 'data' => $empresas]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['nombre' => ['required', 'string', 'max:200'], 'nombre_corto' => ['nullable', 'string', 'max:100'], 'nit' => ['nullable', 'string', 'max:50'], 'logo' => ['nullable', 'string'], 'activo' => ['sometimes', 'boolean']]);
        $empresa = Empresa::query()->create(['activo' => true, ...$validated]);
        return response()->json(['success' => true, 'message' => 'Empresa creada exitosamente.', 'data' => $empresa->toSummaryArray()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $empresa = Empresa::query()->findOrFail($id);
        $validated = $request->validate(['nombre' => ['sometimes', 'required', 'string', 'max:200'], 'nombre_corto' => ['nullable', 'string', 'max:100'], 'nit' => ['nullable', 'string', 'max:50'], 'logo' => ['nullable', 'string'], 'activo' => ['sometimes', 'boolean']]);
        $empresa->update($validated);
        return response()->json(['success' => true, 'message' => 'Empresa actualizada exitosamente.', 'data' => $empresa->fresh()->toSummaryArray()]);
    }

    public function toggleActivo(Request $request, int $id): JsonResponse
    {
        $empresa = Empresa::query()->findOrFail($id);
        $validated = $request->validate(['activo' => ['required', 'boolean']]);
        $empresa->update(['activo' => $validated['activo']]);
        return response()->json(['success' => true, 'message' => $validated['activo'] ? 'Empresa activada.' : 'Empresa desactivada.', 'data' => $empresa->fresh()->toSummaryArray()]);
    }

    public function syncUsuarios(Request $request, int $id): JsonResponse
    {
        $empresa = Empresa::query()->findOrFail($id);
        $validated = $request->validate(['user_ids' => ['required', 'array'], 'user_ids.*' => ['integer', 'exists:users,id']]);
        $empresa->users()->sync($validated['user_ids']);
        return response()->json(['success' => true, 'message' => 'Usuarios asignados correctamente.']);
    }
}
