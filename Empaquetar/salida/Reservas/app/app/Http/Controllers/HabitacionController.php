<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Casa;
use App\Models\Habitacion;
use App\Support\CurrentEmpresa;
use App\Support\MovementScopeFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HabitacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$casaId] = MovementScopeFilter::resolveFromRequest($request->query('casa_id'));
        $query = Habitacion::query()->with('casa')->orderBy('nombre');
        if ($casaId !== null) {
            $query->where('casa_id', $casaId);
        }
        if ($request->boolean('solo_activas', false)) {
            $query->where('activo', true);
        }
        $habitaciones = $query->get()->map(fn (Habitacion $h) => $h->toSummaryArray())->values();
        return response()->json(['success' => true, 'data' => $habitaciones]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $casa = Casa::query()->findOrFail($validated['casa_id']);
        if (empty($validated['color'])) {
            $validated['color'] = $this->nextColorForCasa((int) $casa->id);
        }
        $habitacion = Habitacion::query()->create(['empresa_id' => CurrentEmpresa::id(), 'casa_id' => $casa->id, 'activo' => array_key_exists('activo', $validated) ? (bool) $validated['activo'] : true, ...$validated]);
        $habitacion->load('casa');
        return response()->json(['success' => true, 'message' => 'Habitación creada exitosamente.', 'data' => $habitacion->toSummaryArray()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $habitacion = Habitacion::query()->findOrFail($id);
        $validated = $this->validatePayload($request, true);
        if (isset($validated['casa_id'])) {
            Casa::query()->findOrFail($validated['casa_id']);
        }
        $habitacion->update($validated);
        return response()->json(['success' => true, 'message' => 'Habitación actualizada exitosamente.', 'data' => $habitacion->fresh(['casa'])->toSummaryArray()]);
    }

    public function toggleActivo(Request $request, int $id): JsonResponse
    {
        $habitacion = Habitacion::query()->findOrFail($id);
        $validated = $request->validate(['activo' => ['required', 'boolean']]);
        $habitacion->update(['activo' => $validated['activo']]);
        return response()->json(['success' => true, 'message' => $validated['activo'] ? 'Habitación activada.' : 'Habitación desactivada.', 'data' => $habitacion->fresh(['casa'])->toSummaryArray()]);
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        return $request->validate([
            'casa_id' => [...$required, 'integer', Rule::exists('casas', 'id')->where('empresa_id', CurrentEmpresa::id())],
            'nombre' => [...$required, 'string', 'max:150'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'tipo' => [...$required, Rule::in([Habitacion::TIPO_PRIVADA, Habitacion::TIPO_COMPARTIDA])],
            'capacidad' => [...$required, 'integer', 'min:1'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo' => ['sometimes', 'boolean'],
        ]);
    }

    private function nextColorForCasa(int $casaId): string
    {
        $used = Habitacion::query()->where('casa_id', $casaId)->pluck('color')->filter()->map(fn (string $c) => strtolower($c))->all();
        foreach (Habitacion::COLOR_PALETTE as $color) {
            if (! in_array(strtolower($color), $used, true)) {
                return $color;
            }
        }
        $count = Habitacion::query()->where('casa_id', $casaId)->count();
        return Habitacion::COLOR_PALETTE[$count % count(Habitacion::COLOR_PALETTE)];
    }
}
