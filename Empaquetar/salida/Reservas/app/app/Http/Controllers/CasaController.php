<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Casa;
use App\Support\CurrentEmpresa;
use App\Support\MovementScopeFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CasaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$casaId] = MovementScopeFilter::resolveFromRequest($request->query('casa_id'));
        $query = Casa::query()->where('activo', true)->orderBy('nombre');
        if ($casaId !== null) {
            $query->whereKey($casaId);
        }
        $casas = $query->get()->map(fn (Casa $casa) => $casa->toSummaryArray())->values();
        return response()->json(['success' => true, 'data' => $casas]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['nombre' => ['required', 'string', 'max:150'], 'codigo' => ['nullable', 'string', 'max:50'], 'descripcion' => ['nullable', 'string']]);
        $casa = Casa::query()->create(['empresa_id' => CurrentEmpresa::id(), 'activo' => true, ...$validated]);
        return response()->json(['success' => true, 'message' => 'Casa creada exitosamente.', 'data' => $casa->toSummaryArray()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $casa = Casa::query()->findOrFail($id);
        $validated = $request->validate(['nombre' => ['sometimes', 'required', 'string', 'max:150'], 'codigo' => ['nullable', 'string', 'max:50'], 'descripcion' => ['nullable', 'string']]);
        $casa->update($validated);
        return response()->json(['success' => true, 'message' => 'Casa actualizada exitosamente.', 'data' => $casa->fresh()->toSummaryArray()]);
    }

    public function toggleActivo(Request $request, int $id): JsonResponse
    {
        $casa = Casa::query()->findOrFail($id);
        $validated = $request->validate(['activo' => ['required', 'boolean']]);
        $casa->update(['activo' => $validated['activo']]);
        return response()->json(['success' => true, 'message' => $validated['activo'] ? 'Casa activada.' : 'Casa desactivada.', 'data' => $casa->fresh()->toSummaryArray()]);
    }
}
