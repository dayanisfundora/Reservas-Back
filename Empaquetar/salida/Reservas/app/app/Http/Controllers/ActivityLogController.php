<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Artisan;

class ActivityLogController extends Controller
{
    /**
     * Map Spanish table names to English model names for search
     *
     * @param string $searchTerm
     * @return array
     */
    private function get_model_names_from_spanish(string $searchTerm): array
    {
        $translations = [
            'Moneda' => 'Currency',
            'Entidad' => 'Entity',
            'Provincia' => 'Province',
            'Estado' => 'State',
            'Usuario' => 'User',
            'Origin' => 'Origen'
        ];

        $modelNames = [];

        // Search for matching Spanish terms (case insensitive, partial match)
        foreach ($translations as $spanish => $english) {
            if (stripos($spanish, $searchTerm) !== false) {
                $modelNames[] = $english;
            }
        }

        return $modelNames;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Activity::query();

            // Search filter by id, description, subject_type, event, or created_at
            $search = $request->input('search');
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('created_at', 'like', "%{$search}%")
                        ->orWhereHas('causer', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        });

                    // Search by formatted date and time (d/m/Y, H:i:s format like 3/11/2025, 15:36:17)
                    $q->orWhereRaw("DATE_FORMAT(created_at, '%e/%c/%Y, %H:%i:%s') LIKE ?", ["%{$search}%"])
                        // Search by time only (H:i:s format like 15:36:17)
                        ->orWhereRaw("DATE_FORMAT(created_at, '%H:%i:%s') LIKE ?", ["%{$search}%"])
                        // Search by date only (d/m/Y format like 3/11/2025)
                        ->orWhereRaw("DATE_FORMAT(created_at, '%e/%c/%Y') LIKE ?", ["%{$search}%"]);

                    // Search by Spanish table names
                    $modelNames = $this->get_model_names_from_spanish($search);
                    if (!empty($modelNames)) {
                        foreach ($modelNames as $modelName) {
                            $q->orWhere('subject_type', 'like', "%{$modelName}%");
                        }
                    }
                });
            }

            // Pagination
            $perPage = $request->input('per_page', 5);
            $states = $query->paginate($perPage);

            if ($states->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No hay trazas registradas.'
                ], 200);
            }

            return response()->json([
                'data' => $states->items(),
                'meta' => [
                    'current_page' => $states->currentPage(),
                    'last_page' => $states->lastPage(),
                    'per_page' => $states->perPage(),
                    'total' => $states->total(),
                ]
            ], 200);
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
            $trace = Activity::find($id);
            if (!$trace) {
                return response()->json(['error' => 'Traza no encontrada.'], 404);
            }
            return response()->json(['data' => $trace], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function generatePDF(Request $request)
    {
        try {
            $ids = $request->input('ids', []);

            $query = Activity::query();

            // Apply search filter if included in the request
            $search = $request->input('search');
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('created_at', 'like', "%{$search}%")
                        ->orWhereHas('causer', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        });

                    // Search by formatted date and time (d/m/Y, H:i:s format like 3/11/2025, 15:36:17)
                    $q->orWhereRaw("DATE_FORMAT(created_at, '%e/%c/%Y, %H:%i:%s') LIKE ?", ["%{$search}%"])
                        // Search by time only (H:i:s format like 15:36:17)
                        ->orWhereRaw("DATE_FORMAT(created_at, '%H:%i:%s') LIKE ?", ["%{$search}%"])
                        // Search by date only (d/m/Y format like 3/11/2025)
                        ->orWhereRaw("DATE_FORMAT(created_at, '%e/%c/%Y') LIKE ?", ["%{$search}%"]);

                    // Search by Spanish table names
                    $modelNames = $this->get_model_names_from_spanish($search);
                    if (!empty($modelNames)) {
                        foreach ($modelNames as $modelName) {
                            $q->orWhere('subject_type', 'like', "%{$modelName}%");
                        }
                    }
                });
            }

            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }

            // Obtener TODOS los registros sin paginar
            $allDatos = $query->orderBy('id', 'desc')->get();

            // Ensure properties is an array for each record
            $allDatos->transform(function ($activity) {
                if (isset($activity->properties) && is_object($activity->properties)) {
                    $activity->properties = json_decode(json_encode($activity->properties), true);
                }
                return $activity;
            });

            $entity = Entity::find(1);
            $entityName = $entity ? $entity->name : 'Nombre de la Entidad';
            $entityLogo = $entity && $entity->logo ? $entity->logo : null;

            // Generar el PDF con todos los datos
            $pdf = Pdf::loadView('pdf.trace', [
                'datos' => $allDatos,
                'currentPage' => 1,
                'lastPage' => 1,
                'total' => $allDatos->count(),
                'entityName' => $entityName,
                'entityLogo' => $entityLogo,
            ]);

            $pdf->setPaper('A4', 'portrait');

            if ($request->input('view') == 'browser') {
                return $pdf->stream('trace.pdf');
            }

            return $pdf->download('trace.pdf');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }
}
