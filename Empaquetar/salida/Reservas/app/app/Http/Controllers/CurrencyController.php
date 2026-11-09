<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrencyRequest;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Siclamon;
use Dotenv\Parser\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Sync currencies from SICLAMON before returning data
            Artisan::call('currencies:sync-from-siclamon', ['--update' => true]);
            
            $query = Currency::query();

            // Global search filter
            $search = $request->input('search');
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('abbr', 'like', "%{$search}%")
                        ->orWhere('rate', 'like', "%{$search}%");
                });
            }

            // Exact filters
            if ($request->filled('code')) {
                $query->where('code', $request->input('code'));
            }
            if ($request->filled('description')) {
                $query->where('description', 'like', "%{$request->input('description')}%");
            }
            if ($request->filled('abbr')) {
                $query->where('abbr', 'like', "%{$request->input('abbr')}%");
            }
            if ($request->filled('rate')) {
                $query->where('rate', $request->input('rate'));
            }
            if ($request->filled('base_currency')) {
                $query->where('base_currency', $request->input('base_currency'));
            }

            // Flexible sorting
            $allowedSorts = ['code', 'description', 'abbr', 'rate', 'base_currency', 'id', 'created_at', 'updated_at'];
            $sortBy = $request->input('sort_by', 'base_currency');
            $sortOrder = $request->input('sort_order', 'desc');
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'base_currency';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                $sortOrder = 'desc';
            }
            $query->orderBy($sortBy, $sortOrder)->orderBy('code');

            // Robust pagination
            $perPage = (int) $request->input('per_page', 5);
            if ($perPage < 1 || $perPage > 100) {
                $perPage = 5;
            }
            $currencies = $query->paginate($perPage);

            if ($currencies->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No hay monedas registradas.'
                ], 200);
            }

            return response()->json([
                'data' => $currencies->items(),
                'meta' => [
                    'current_page' => $currencies->currentPage(),
                    'last_page' => $currencies->lastPage(),
                    'per_page' => $currencies->perPage(),
                    'total' => $currencies->total(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

     /**
     * Check if a field value is unique in the database
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function check_unique(Request $request)
    {
        try {
            $field = $request->input('field');
            $value = $request->input('value');
            $id = $request->input('id');

            // Validate required parameters
            if (!$field || !$value) {
                return response()->json([
                    'is_unique' => true,
                    'message' => 'Parámetros insuficientes'
                ], 400);
            }

            // Allowed fields to check
            $allowed_fields = ['code', 'description', 'abbr', 'rate'];
            if (!in_array($field, $allowed_fields)) {
                return response()->json([
                    'is_unique' => true,
                    'message' => 'Campo no válido'
                ], 400);
            }

            // Build query
            $query = Currency::where($field, trim($value));

            // Exclude current record if editing
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

    public function indexWithoutPagination()
    {
        try {
            // Sync currencies from SICLAMON before returning data
            Artisan::call('currencies:sync-from-siclamon', ['--update' => true]);
            
            $date = Currency::select('description', 'abbr', 'id', 'rate', 'base_currency')->get();

            if ($date->isEmpty()) {
                throw new \Exception("No hay datos registrados", 404);
            }

            return $date;
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                return response()->json(['error' => $e->getMessage()], $e->getCode());
            }
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CurrencyRequest $request)
    {
        try {
            $data = $request->validated();
            // Convert the decimal in rate to a comma if it comes with a period
            if (isset($data['rate'])) {
                $data['rate'] = str_replace('.', ',', $data['rate']);
            }
            $state = Currency::create($data);
            return response()->json([
                'message' => 'Moneda creada exitosamente.',
                'data' => $state
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
            $state = Currency::find($id);
            if (!$state) {
                return response()->json(['error' => 'Moneda no encontrada.'], 404);
            }
            return response()->json(['data' => $state], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CurrencyRequest $request, string $id)
    {
        try {
            $currency = Currency::find($id);
            if (!$currency) {
                return response()->json(['error' => 'Moneda no encontrada.'], 404);
            }
            $data = $request->validated();
            // Convert the decimal in rate to a comma if it comes with a period
            if (isset($data['rate'])) {
                $data['rate'] = str_replace('.', ',', $data['rate']);
            }
            $currency->update($data);
            return response()->json([
                'message' => 'Moneada actualizada exitosamente.',
                'data' => $currency
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
            $state = Currency::find($id);
            if (!$state) {
                return response()->json(['error' => 'Moneda no encontrada.'], 404);
            }
            $state->delete();
            return response()->json(['message' => 'Moneda eliminada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function generatePDF(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            $perPage = 31;
            $page = $request->input('page', 1);
            $query = Currency::query();

            // Apply search filter if included in the request
            $search = $request->input('search');
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }

            // Sort first by base_currency (desc), then by state (desc), then by code
            $datos = $query->orderByDesc('base_currency')
                ->orderByDesc('state')
                ->orderBy('code')
                ->paginate($perPage);

            $entity = Entity::find(1);
            $entityName = $entity ? $entity->name : 'Nombre de la Entidad';
            $entityLogo = $entity && $entity->logo ? $entity->logo : null;

            $pdf = Pdf::loadView('pdf.currency', [
                'datos' => $datos,
                'currentPage' => $page,
                'lastPage' => $datos->lastPage(),
                'total' => $datos->total(),
                'entityName' => $entityName,
                'entityLogo' => $entityLogo,
            ]);

            $pdf->setPaper('A4', 'portrait');

            if ($request->input('view') == 'browser') {
                return $pdf->stream('currency.pdf');
            }

            return $pdf->download('currency.pdf');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display a listing of currencies from ihsecurity database (SICLAMON table)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index_siclamon(Request $request)
    {
        try {
            $query = Siclamon::query();

            // Global search filter
            $search = $request->input('search');
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('cod_mone', 'like', "%{$search}%")
                        ->orWhere('desc_mone', 'like', "%{$search}%")
                        ->orWhere('tasa', 'like', "%{$search}%")
                        ->orWhere('comision', 'like', "%{$search}%");
                });
            }

            // Exact filters
            if ($request->filled('cod_mone')) {
                $query->where('cod_mone', $request->input('cod_mone'));
            }
            if ($request->filled('desc_mone')) {
                $query->where('desc_mone', 'like', "%{$request->input('desc_mone')}%");
            }
            if ($request->filled('tasa')) {
                $query->where('tasa', $request->input('tasa'));
            }
            if ($request->filled('comision')) {
                $query->where('comision', $request->input('comision'));
            }
            if ($request->filled('decimales')) {
                $query->where('decimales', $request->input('decimales'));
            }
            if ($request->filled('convertible')) {
                $query->where('convertible', $request->input('convertible'));
            }
            if ($request->filled('facturable')) {
                $query->where('facturable', $request->input('facturable'));
            }

            // Flexible sorting
            $allowed_sorts = ['cod_mone', 'desc_mone', 'tasa', 'comision', 'decimales', 'convertible', 'facturable'];
            $sort_by = $request->input('sort_by', 'cod_mone');
            $sort_order = $request->input('sort_order', 'asc');
            if (!in_array($sort_by, $allowed_sorts)) {
                $sort_by = 'cod_mone';
            }
            if (!in_array(strtolower($sort_order), ['asc', 'desc'])) {
                $sort_order = 'asc';
            }
            $query->orderBy($sort_by, $sort_order);

            // Robust pagination
            $per_page = (int) $request->input('per_page', 10);
            if ($per_page < 1 || $per_page > 100) {
                $per_page = 10;
            }
            $currencies = $query->paginate($per_page);

            if ($currencies->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No hay monedas registradas en SICLAMON.'
                ], 200);
            }

            return response()->json([
                'data' => $currencies->items(),
                'meta' => [
                    'current_page' => $currencies->currentPage(),
                    'last_page' => $currencies->lastPage(),
                    'per_page' => $currencies->perPage(),
                    'total' => $currencies->total(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching SICLAMON data: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync currencies from SICLAMON table to local currencies table
     * 
     * This method triggers the Artisan command to synchronize currency data
     * from the ihsecurity database (SICLAMON table) to the local currencies table.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sync_from_siclamon(Request $request)
    {
        try {
            $update_existing = $request->input('update', false);
            
            $siclamon_currencies = Siclamon::all();

            if ($siclamon_currencies->isEmpty()) {
                return response()->json([
                    'message' => 'No hay monedas en SICLAMON para sincronizar.',
                    'data' => [
                        'created' => 0,
                        'updated' => 0,
                        'skipped' => 0,
                        'total' => 0,
                    ]
                ], 200);
            }

            $created_count = 0;
            $updated_count = 0;
            $skipped_count = 0;

            foreach ($siclamon_currencies as $siclamon) {
                $existing = Currency::where('code', $siclamon->id_mone)->first();

                // Prepare data for currency
                // New mapping: code = id_mone, abbr = cod_mone
                $currency_data = [
                    'code' => $siclamon->id_mone,
                    'description' => $siclamon->desc_mone,
                    'abbr' => $siclamon->cod_mone,
                    'rate' => (float) str_replace(',', '.', $siclamon->tasa),
                    'base_currency' => ($siclamon->cod_mone === 'CUP'), // Only CUP is base currency
                    'convertible' => (bool) $siclamon->convertible,
                    'facturable' => (bool) $siclamon->facturable,
                    'state' => true,
                ];

                if ($existing) {
                    if ($update_existing) {
                        $existing->update($currency_data);
                        $updated_count++;
                    } else {
                        $skipped_count++;
                    }
                } else {
                    Currency::create($currency_data);
                    $created_count++;
                }
            }

            Log::info('Currency sync completed via API', [
                'created' => $created_count,
                'updated' => $updated_count,
                'skipped' => $skipped_count,
            ]);

            return response()->json([
                'message' => 'Sincronización completada exitosamente.',
                'data' => [
                    'created' => $created_count,
                    'updated' => $updated_count,
                    'skipped' => $skipped_count,
                    'total' => $siclamon_currencies->count(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Currency sync failed via API: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al sincronizar monedas: ' . $e->getMessage()
            ], 500);
        }
    }
}
