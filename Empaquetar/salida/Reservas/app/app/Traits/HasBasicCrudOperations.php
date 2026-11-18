<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Trait HasBasicCrudOperations
 * Provides reusable CRUD and utility methods for basic controllers.
 *
 * Usage example:
 *   class StateController extends Controller {
 *     use HasBasicCrudOperations;
 *     protected string $modelClass = State::class;
 *     protected array $uniqueFields = ['code', 'description'];
 *     protected array $allowedSorts = ['code', 'description', 'state', 'id', 'created_at', 'updated_at'];
 *     protected array $searchFields = ['code', 'description'];
 *     // Optionally override filterQuery($query, $request) for custom filters
 *   }
 */
trait HasBasicCrudOperations
{
    /**
     * The Eloquent model class to use.
     * @var string
     */
    protected string $modelClass;

    /**
     * Fields to check for uniqueness.
     * @var array
     */
    protected array $uniqueFields = [];

    /**
     * Allowed fields for sorting.
     * @var array
     */
    protected array $allowedSorts = ['id'];

    /**
     * Fields to use for global search.
     * @var array
     */
    protected array $searchFields = [];

    /**
     * PDF view name for generatePDF method.
     * @var string
     */
    protected string $pdfViewName = 'pdf.generic';

    /**
     * Display a listing of the resource with filters, sorting, and pagination.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $modelClass = $this->modelClass;
            $query = $modelClass::query();

            // Global search filter
            $search = $request->input('search');
            if ($search && !empty($this->searchFields)) {
                $query->where(function ($q) use ($search) {
                    foreach ($this->searchFields as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            }

            // Custom filters (can be overridden)
            $query = $this->filterQuery($query, $request);

            // Sorting
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');
            if (!in_array($sortBy, $this->allowedSorts)) {
                $sortBy = 'id';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                $sortOrder = 'asc';
            }
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = (int) $request->input('per_page', 5);
            if ($perPage < 1 || $perPage > 100) {
                $perPage = 5;
            }
            $items = $query->paginate($perPage);

            if ($items->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No hay registros.'
                ], 200);
            }

            return response()->json([
                'data' => $items->items(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Custom filter logic for queries. Override in controller if needed.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function filterQuery($query, Request $request)
    {
        // Default: no extra filters
        return $query;
    }

    /**
     * Get the next available code (numeric, padded).
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNextCode()
    {
        try {
            $modelClass = $this->modelClass;
            $maxCode = $modelClass::all()
                ->pluck('code')
                ->filter(fn($code) => is_numeric($code))
                ->map(fn($code) => intval($code))
                ->max();
            $nextCode = $maxCode ? str_pad(strval($maxCode + 1), 3, '0', STR_PAD_LEFT) : '001';
            return response()->json(['next_code' => $nextCode], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $data = method_exists($request, 'validated') ? $request->validated() : $request->all();
        return $this->handleStore($data);
    }

    /**
     * Handle the store logic with validated data.
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleStore(array $data)
    {
        try {
            $modelClass = $this->modelClass;
            $maxCode = $modelClass::all()
                ->pluck('code')
                ->filter(fn($code) => is_numeric($code))
                ->map(fn($code) => intval($code))
                ->max();
            $nextCode = $maxCode ? str_pad(strval($maxCode + 1), 3, '0', STR_PAD_LEFT) : '001';
            
            $data['code'] = $nextCode;
            $item = $modelClass::create($data);
            return response()->json([
                'message' => 'Registro creado exitosamente.',
                'data' => $item
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check if a field value is unique in the database.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function check_unique(Request $request)
    {
        try {
            $field = $request->input('field');
            $value = $request->input('value');
            $id = $request->input('id');
            if (!$field || !$value) {
                return response()->json([
                    'is_unique' => true,
                    'message' => 'Parámetros insuficientes'
                ], 400);
            }
            if (!in_array($field, $this->uniqueFields)) {
                return response()->json([
                    'is_unique' => true,
                    'message' => 'Campo no válido'
                ], 400);
            }
            $modelClass = $this->modelClass;
            $query = $modelClass::where($field, trim($value));
            if ($id) {
                $query->where('id', '!=', $id);
            }
            $exists = $query->exists();
            return response()->json([
                'is_unique' => !$exists,
                'field' => $field,
                'value' => $value
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error checking unique value: ' . $e->getMessage());
            return response()->json([
                'is_unique' => true,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        try {
            $modelClass = $this->modelClass;
            $item = $modelClass::find($id);
            if (!$item) {
                return response()->json(['error' => 'Registro no encontrado.'], 404);
            }
            return response()->json(['data' => $item], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id)
    {
        $data = method_exists($request, 'validated') ? $request->validated() : $request->all();
        return $this->handleUpdate($id, $data);
    }

    /**
     * Handle the update logic with validated data.
     * @param string $id
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleUpdate(string $id, array $data)
    {
        try {
            $modelClass = $this->modelClass;
            $item = $modelClass::find($id);
            if (!$item) {
                return response()->json(['error' => 'Registro no encontrado.'], 404);
            }
            $item->update($data);
            return response()->json([
                'message' => 'Registro actualizado exitosamente.',
                'data' => $item
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        try {
            $modelClass = $this->modelClass;
            $item = $modelClass::find($id);
            if (!$item) {
                return response()->json(['error' => 'Registro no encontrado.'], 404);
            }
            $item->delete();
            return response()->json(['message' => 'Registro eliminado exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate PDF with filtered data
     * @param Request $request
     * @return mixed
     */
    public function generatePDF(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            $perPage = 31;
            $page = $request->input('page', 1);

            $modelClass = $this->modelClass;
            $query = $modelClass::query();

            // Apply search filter if included in the request
            $search = $request->input('search');
            if ($search && !empty($this->searchFields)) {
                $query->where(function ($q) use ($search) {
                    foreach ($this->searchFields as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            }

            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }

            $datos = $query->orderBy('code')->paginate($perPage);

            // Get entity info
            $entity = \App\Models\Entity::find(1);
            $entityName = $entity ? $entity->name : 'Nombre de la Entidad';
            $entityLogo = $entity && $entity->logo ? $entity->logo : null;

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($this->pdfViewName, [
                'datos' => $datos,
                'currentPage' => $page,
                'lastPage' => $datos->lastPage(),
                'total' => $datos->total(),
                'entityName' => $entityName,
                'entityLogo' => $entityLogo,
            ]);

            $pdf->setPaper('A4', 'portrait');

            if ($request->input('view') == 'browser') {
                return $pdf->stream(strtolower(class_basename($modelClass)) . '.pdf');
            }

            $allPages = '';
            for ($i = 1; $i <= $datos->lastPage(); $i++) {
                $currentPageData = $modelClass::query();
                
                // Reapply search filters
                if ($search && !empty($this->searchFields)) {
                    $currentPageData->where(function ($q) use ($search) {
                        foreach ($this->searchFields as $field) {
                            $q->orWhere($field, 'like', "%{$search}%");
                        }
                    });
                }
                
                if (!empty($ids)) {
                    $currentPageData->whereIn('id', $ids);
                }
                
                $currentPageData = $currentPageData->orderBy('code')->paginate($perPage, ['*'], 'page', $i);

                $view = view($this->pdfViewName, [
                    'datos' => $currentPageData,
                    'currentPage' => $i,
                    'lastPage' => $datos->lastPage(),
                    'total' => $datos->total(),
                    'entityName' => $entityName,
                    'entityLogo' => $entityLogo,
                ])->render();

                $allPages .= $view;
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHtml($allPages);
            $pdf->setPaper('A4', 'portrait');

            return $pdf->download(strtolower(class_basename($modelClass)) . '.pdf');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all data without pagination
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function indexWithoutPagination()
    {
        try {
            $modelClass = $this->modelClass;
            $data = $modelClass::all();
            if ($data->isEmpty()) {
                throw new \Exception("No hay datos registrados", 404);
            }
            return $data;
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                return response()->json(['error' => $e->getMessage()], $e->getCode());
            }
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all data with optional filters and large pagination
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll(Request $request)
    {
        try {
            $modelClass = $this->modelClass;
            $query = $modelClass::query();
            // Example: filter by state if present
            if ($request->has('state')) {
                $query->where('state', $request->input('state'));
            }
            $perPage = $request->input('per_page', 1000);
            $page = $request->input('page', 1);
            $items = $query->paginate($perPage, ['*'], 'page', $page);
            return response()->json([
                'data' => $items->items(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
