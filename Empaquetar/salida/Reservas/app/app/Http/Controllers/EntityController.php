<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Http\Requests\EntityRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EntityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Entity::query();

            // Paginación
            $perPage = $request->input('per_page', 5);
            $entities = $query->paginate($perPage);

            if ($entities->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No hay entidades registradas.'
                ], 200);
            }

            return response()->json([
                'data' => $entities->items(),
                'meta' => [
                    'current_page' => $entities->currentPage(),
                    'last_page' => $entities->lastPage(),
                    'per_page' => $entities->perPage(),
                    'total' => $entities->total(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            if (Entity::count() > 0) {
                return response()->json(['error' => 'Ya existe una entidad registrada. Solo se permite una.'], 409);
            }

            $data = [
                'name' => $request->input('name', ''),
                'abbr' => $request->input('abbr', ''),
            ];

            if (empty($data['name']) || empty($data['abbr'])) {
                return response()->json(['error' => 'Nombre y Abreviatura son obligatorios.'], 422);
            }

            // Manejar el logo si viene en la petición
            if ($request->hasFile('logo')) {
                $request->validate([
                    'logo' => 'image|mimes:jpeg,png,svg|max:2048',
                ]);

                $file = $request->file('logo');
                $extension = $file->getClientOriginalExtension();
                
                // Crear nombre personalizado usando la abreviatura de la entidad
                $customFileName = 'logo_' . strtolower($data['abbr']) . '.' . $extension;
                
                // Guardar con nombre personalizado
                $logoPath = $file->storeAs('entity_logos', $customFileName, 'public');
                $data['logo'] = $logoPath;
            }

            $entity = Entity::create($data);

            return response()->json([
                'message' => 'Entidad creada exitosamente.',
                'data' => $entity
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $entity = Entity::find($id);
            if (!$entity) {
                return response()->json(['error' => 'Entidad no encontrada.'], 404);
            }
            return response()->json(['data' => $entity], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            if (Entity::count() === 0) {
                return response()->json(['error' => 'No existe ninguna entidad para actualizar.'], 404);
            }
            
            $entity = Entity::find($id);
            if (!$entity) {
                return response()->json(['error' => 'Entidad no encontrada.'], 404);
            }

            $data = [
                'name' => $request->has('name') ? $request->input('name') : $entity->name,
                'abbr' => $request->has('abbr') ? $request->input('abbr') : $entity->abbr,
            ];

            if (empty($data['name']) || empty($data['abbr'])) {
                return response()->json(['error' => 'Nombre y Abreviatura son obligatorios.'], 422);
            }

            // Manejar el logo
            if ($request->hasFile('logo')) {
                $request->validate([
                    'logo' => 'image|mimes:jpeg,png,svg|max:2048',
                ]);

                // Eliminar logo anterior si existe
                if ($entity->logo && Storage::disk('public')->exists($entity->logo)) {
                    Storage::disk('public')->delete($entity->logo);
                }

                // Guardar nuevo logo con nombre personalizado
                $file = $request->file('logo');
                $extension = $file->getClientOriginalExtension();
                $customFileName = 'logo_' . strtolower($data['abbr']) . '.' . $extension;
                $logoPath = $file->storeAs('entity_logos', $customFileName, 'public');
                // Copiar también a public/storage/entity_logos si no existe (por si el symlink no funciona)
                $publicStoragePath = public_path('storage/entity_logos');
                if (!file_exists($publicStoragePath)) {
                    mkdir($publicStoragePath, 0777, true);
                }
                copy(storage_path('app/public/' . $logoPath), $publicStoragePath . DIRECTORY_SEPARATOR . $customFileName);
                $data['logo'] = $logoPath;
            } else if ($request->filled('logo') && !$request->input('logo')) {
                // Si el logo viene explícitamente vacío, borrar el logo
                if ($entity->logo && Storage::disk('public')->exists($entity->logo)) {
                    Storage::disk('public')->delete($entity->logo);
                }
                $data['logo'] = null;
            } else {
                // Si no viene el campo logo o viene como string (no se subió archivo), mantener el valor actual
                $data['logo'] = $entity->logo;
            }

            $entity->update($data);

            return response()->json([
                'message' => 'Entidad actualizada exitosamente.',
                'data' => $entity
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $entity = Entity::find($id);
            if (!$entity) {
                return response()->json(['error' => 'Entidad no encontrada.'], 404);
            }

            // Eliminar logo si existe
            if ($entity->logo && Storage::disk('public')->exists($entity->logo)) {
                Storage::disk('public')->delete($entity->logo);
            }

            $entity->delete();
            return response()->json(['message' => 'Entidad eliminada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
