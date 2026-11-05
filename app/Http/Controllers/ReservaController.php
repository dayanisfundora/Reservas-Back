<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\Casa;
use App\Models\Habitacion;
use App\Models\Reserva;
use App\Services\ReservationAvailabilityService;
use App\Support\CurrentEmpresa;
use App\Support\MovementScopeFilter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservaController extends Controller
{
    public function __construct(
        private readonly ReservationAvailabilityService $availability,
    ) {}

    public function index(Request $request): JsonResponse
    {
        [$casaId] = MovementScopeFilter::resolveFromRequest($request->query('casa_id'));

        $query = Reserva::query()
            ->with(['habitacion', 'casa'])
            ->orderByDesc('fecha_entrada')
            ->orderByDesc('id');

        if ($casaId !== null) {
            $query->where('casa_id', $casaId);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        if ($request->filled('desde')) {
            $query->where('fecha_salida', '>', $request->query('desde'));
        }

        if ($request->filled('hasta')) {
            $query->where('fecha_entrada', '<=', $request->query('hasta'));
        }

        if ($request->filled('habitacion_id')) {
            $query->where('habitacion_id', (int) $request->query('habitacion_id'));
        }

        $reservas = $query->get()->map(fn (Reserva $r) => $r->toSummaryArray())->values();

        return response()->json([
            'success' => true,
            'data' => $reservas,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        [$habitacion, $entrada, $salida, $noches] = $this->resolveStay($validated);
        $normalized = $this->normalizeGenderAndBeds($habitacion, $validated);

        $this->availability->assertAvailable(
            $habitacion,
            $entrada,
            $salida,
            (int) $validated['cantidad_personas'],
            null,
            $normalized['genero']
        );

        $reserva = Reserva::query()->create([
            'empresa_id' => CurrentEmpresa::id(),
            'casa_id' => $habitacion->casa_id,
            'habitacion_id' => $habitacion->id,
            'cliente_nombre' => $validated['cliente_nombre'],
            'genero' => $normalized['genero'],
            'generos' => $normalized['generos'],
            'agencia' => $validated['agencia'] ?? null,
            'hora_llegada' => $validated['hora_llegada'] ?? null,
            'vuelo' => $validated['vuelo'] ?? null,
            'taxi_llegada' => (bool) ($validated['taxi_llegada'] ?? false),
            'taxi_salida' => (bool) ($validated['taxi_salida'] ?? false),
            'cantidad_personas' => (int) $validated['cantidad_personas'],
            'camas' => $normalized['camas'],
            'cantidad_noches' => $noches,
            'fecha_entrada' => $entrada->toDateString(),
            'fecha_salida' => $salida->toDateString(),
            'estado' => Reserva::ESTADO_PENDIENTE,
            'notas' => $validated['notas'] ?? null,
        ]);

        $reserva->load(['habitacion', 'casa']);

        return response()->json([
            'success' => true,
            'message' => 'Reserva creada exitosamente (pendiente a confirmar).',
            'data' => $reserva->toSummaryArray(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $reserva = Reserva::query()->findOrFail($id);

        if ($reserva->estado === Reserva::ESTADO_CANCELADA) {
            throw ValidationException::withMessages([
                'estado' => ['No se puede editar una reserva cancelada.'],
            ]);
        }

        $validated = $this->validatePayload($request, partial: true);
        $payload = array_merge($reserva->only([
            'habitacion_id',
            'cliente_nombre',
            'genero',
            'generos',
            'agencia',
            'hora_llegada',
            'vuelo',
            'taxi_llegada',
            'taxi_salida',
            'cantidad_personas',
            'camas',
            'cantidad_noches',
            'fecha_entrada',
            'notas',
        ]), $validated);

        if (! isset($validated['fecha_entrada'])) {
            $payload['fecha_entrada'] = $reserva->fecha_entrada->format('Y-m-d');
        }
        if (! isset($validated['cantidad_noches'])) {
            $payload['cantidad_noches'] = $reserva->cantidad_noches;
        }
        if (! isset($validated['cantidad_personas'])) {
            $payload['cantidad_personas'] = $reserva->cantidad_personas;
        }
        if (! isset($validated['habitacion_id'])) {
            $payload['habitacion_id'] = $reserva->habitacion_id;
        }
        if (! array_key_exists('camas', $validated)) {
            $payload['camas'] = $reserva->camas;
        }
        if (! array_key_exists('generos', $validated)) {
            $payload['generos'] = $reserva->generos;
        }
        if (! array_key_exists('genero', $validated)) {
            $payload['genero'] = $reserva->genero;
        }

        [$habitacion, $entrada, $salida, $noches] = $this->resolveStay($payload, $reserva);
        $normalized = $this->normalizeGenderAndBeds($habitacion, $payload);

        $this->availability->assertAvailable(
            $habitacion,
            $entrada,
            $salida,
            (int) $payload['cantidad_personas'],
            $reserva->id,
            $normalized['genero']
        );

        $reserva->update([
            'casa_id' => $habitacion->casa_id,
            'habitacion_id' => $habitacion->id,
            'cliente_nombre' => $payload['cliente_nombre'],
            'genero' => $normalized['genero'],
            'generos' => $normalized['generos'],
            'agencia' => $payload['agencia'] ?? null,
            'hora_llegada' => $payload['hora_llegada'] ?? null,
            'vuelo' => $payload['vuelo'] ?? null,
            'taxi_llegada' => (bool) ($payload['taxi_llegada'] ?? false),
            'taxi_salida' => (bool) ($payload['taxi_salida'] ?? false),
            'cantidad_personas' => (int) $payload['cantidad_personas'],
            'camas' => $normalized['camas'],
            'cantidad_noches' => $noches,
            'fecha_entrada' => $entrada->toDateString(),
            'fecha_salida' => $salida->toDateString(),
            'notas' => $payload['notas'] ?? null,
        ]);

        $reserva->load(['habitacion', 'casa']);

        return response()->json([
            'success' => true,
            'message' => 'Reserva actualizada exitosamente.',
            'data' => $reserva->fresh(['habitacion', 'casa'])->toSummaryArray(),
        ]);
    }

    public function cancelar(int $id): JsonResponse
    {
        $reserva = Reserva::query()->findOrFail($id);

        if ($reserva->estado === Reserva::ESTADO_CANCELADA) {
            return response()->json([
                'success' => true,
                'message' => 'La reserva ya estaba cancelada.',
                'data' => $reserva->load(['habitacion', 'casa'])->toSummaryArray(),
            ]);
        }

        $reserva->update(['estado' => Reserva::ESTADO_CANCELADA]);
        $reserva->load(['habitacion', 'casa']);

        return response()->json([
            'success' => true,
            'message' => 'Reserva cancelada.',
            'data' => $reserva->toSummaryArray(),
        ]);
    }

    public function confirmar(int $id): JsonResponse
    {
        $reserva = Reserva::query()->findOrFail($id);

        if ($reserva->estado === Reserva::ESTADO_CONFIRMADA) {
            return response()->json([
                'success' => true,
                'message' => 'La reserva ya estaba confirmada.',
                'data' => $reserva->load(['habitacion', 'casa'])->toSummaryArray(),
            ]);
        }

        if ($reserva->estado !== Reserva::ESTADO_PENDIENTE) {
            throw ValidationException::withMessages([
                'estado' => ['Solo se pueden confirmar reservas pendientes.'],
            ]);
        }

        $reserva->update(['estado' => Reserva::ESTADO_CONFIRMADA]);
        $reserva->load(['habitacion', 'casa']);

        return response()->json([
            'success' => true,
            'message' => 'Reserva confirmada.',
            'data' => $reserva->toSummaryArray(),
        ]);
    }

    public function proximasLlegadas(Request $request): JsonResponse
    {
        [$casaId] = MovementScopeFilter::resolveFromRequest($request->query('casa_id'));

        if ($casaId === null) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $hoy = Carbon::today()->startOfDay();
        $manana = $hoy->copy()->addDay();

        $reservas = Reserva::query()
            ->with(['habitacion'])
            ->where('casa_id', $casaId)
            ->whereIn('estado', Reserva::ESTADOS_ACTIVOS)
            ->whereBetween('fecha_entrada', [$hoy->toDateString(), $manana->toDateString()])
            ->orderBy('fecha_entrada')
            ->orderByRaw('CASE WHEN hora_llegada IS NULL THEN 1 ELSE 0 END')
            ->orderBy('hora_llegada')
            ->orderBy('id')
            ->get()
            ->map(function (Reserva $r) {
                $summary = $r->toSummaryArray();

                return [
                    'id' => $summary['id'],
                    'cliente_nombre' => $summary['cliente_nombre'],
                    'habitacion_id' => $summary['habitacion_id'],
                    'habitacion_nombre' => $summary['habitacion']['nombre'] ?? 'Habitación',
                    'fecha_entrada' => $summary['fecha_entrada'],
                    'hora_llegada' => $summary['hora_llegada'],
                    'estado' => $summary['estado'],
                    'taxi_llegada' => $summary['taxi_llegada'],
                    'taxi_salida' => $summary['taxi_salida'],
                    'cantidad_personas' => $summary['cantidad_personas'],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $reservas,
        ]);
    }

    public function disponibilidad(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'habitacion_id' => ['required', 'integer', Rule::exists('habitaciones', 'id')],
            'fecha_entrada' => ['required', 'date'],
            'cantidad_noches' => ['nullable', 'integer', 'min:1'],
            'fecha_salida' => ['nullable', 'date', 'after:fecha_entrada'],
            'cantidad_personas' => ['required', 'integer', 'min:1'],
            'exclude_reserva_id' => ['nullable', 'integer'],
            'genero' => ['nullable', Rule::in([Reserva::GENERO_FEMENINO, Reserva::GENERO_MASCULINO])],
        ]);

        $habitacion = Habitacion::query()->findOrFail($validated['habitacion_id']);
        $entrada = Carbon::parse($validated['fecha_entrada'])->startOfDay();

        if (! empty($validated['cantidad_noches'])) {
            $salida = $this->availability->resolveSalida($entrada, (int) $validated['cantidad_noches']);
        } elseif (! empty($validated['fecha_salida'])) {
            $salida = Carbon::parse($validated['fecha_salida'])->startOfDay();
        } else {
            throw ValidationException::withMessages([
                'cantidad_noches' => ['Debe indicar cantidad de noches o fecha de salida.'],
            ]);
        }

        $result = $this->availability->check(
            $habitacion,
            $entrada,
            $salida,
            (int) $validated['cantidad_personas'],
            isset($validated['exclude_reserva_id']) ? (int) $validated['exclude_reserva_id'] : null,
            $validated['genero'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => [
                ...$result,
                'fecha_entrada' => $entrada->format('Y-m-d'),
                'fecha_salida' => $salida->format('Y-m-d'),
                'cantidad_noches' => $entrada->diffInDays($salida),
            ],
        ]);
    }

    public function calendario(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'casa_id' => ['nullable', 'integer'],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $casaIdParam = $validated['casa_id'] ?? $request->query('casa_id');
        [$casaId] = MovementScopeFilter::resolveFromRequest(
            $casaIdParam !== null && $casaIdParam !== '' ? (string) $casaIdParam : null
        );

        if ($casaId === null) {
            throw ValidationException::withMessages([
                'casa_id' => ['Debe indicar una casa para el calendario.'],
            ]);
        }

        Casa::query()->findOrFail($casaId);

        $desde = Carbon::parse($validated['desde'])->startOfDay();
        $hasta = Carbon::parse($validated['hasta'])->startOfDay();

        if ($desde->diffInDays($hasta) > 62) {
            throw ValidationException::withMessages([
                'hasta' => ['El rango del calendario no puede superar 62 días.'],
            ]);
        }

        $data = $this->availability->calendar($casaId, $desde, $hasta);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function calendarioPdf(Request $request)
    {
        $validated = $request->validate([
            'casa_id' => ['required', 'integer', Rule::exists('casas', 'id')],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'view_mode' => ['nullable', Rule::in(['dia', 'mensual', 'libro'])],
            'selected_date' => ['nullable', 'date'],
            'habitacion_ids' => ['nullable', 'array'],
            'habitacion_ids.*' => ['integer'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $casaId = (int) $validated['casa_id'];
        $desde = Carbon::parse($validated['desde'])->startOfDay();
        $hasta = Carbon::parse($validated['hasta'])->startOfDay();

        if ($desde->diffInDays($hasta) > 62) {
            throw ValidationException::withMessages([
                'hasta' => ['El rango del calendario no puede superar 62 días.'],
            ]);
        }

        $viewMode = (string) ($validated['view_mode'] ?? 'mensual');
        $selectedDate = isset($validated['selected_date'])
            ? Carbon::parse($validated['selected_date'])->format('Y-m-d')
            : $desde->format('Y-m-d');
        $selectedRoomIds = collect($validated['habitacion_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));

        $calendar = $this->availability->calendar($casaId, $desde, $hasta);
        $habitaciones = collect($calendar['habitaciones']);
        if (! empty($selectedRoomIds)) {
            $habitaciones = $habitaciones->whereIn('id', $selectedRoomIds)->values();
        }

        $rowsByDay = [];
        foreach ($habitaciones as $room) {
            $roomColor = (string) ($room['color'] ?? '#0f766e');
            foreach (($room['dias'] ?? []) as $cell) {
                $day = (string) ($cell['fecha'] ?? '');
                foreach (($cell['reservas'] ?? []) as $reserva) {
                    $rowsByDay[$day][$reserva['id']] = [
                        ...$reserva,
                        'habitacion_id' => $room['id'] ?? null,
                        'habitacion_nombre' => $room['nombre'] ?? 'Habitacion',
                        'habitacion_color' => $roomColor,
                    ];
                }
            }
        }

        if ($search !== '') {
            foreach ($rowsByDay as $day => $rows) {
                $filtered = array_filter($rows, function (array $r) use ($search): bool {
                    $text = mb_strtolower(implode(' ', [
                        $r['cliente_nombre'] ?? '',
                        $r['habitacion_nombre'] ?? '',
                        $r['agencia'] ?? '',
                    ]));

                    return str_contains($text, $search);
                });
                $rowsByDay[$day] = $filtered;
            }
        }

        ksort($rowsByDay);

        $weeks = [];
        $cursor = $desde->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $hasta->copy()->endOfWeek(Carbon::SUNDAY);
        while ($cursor->lte($weekEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $iso = $cursor->format('Y-m-d');
                $inRange = $cursor->betweenIncluded($desde, $hasta);
                $dayReservas = array_values($rowsByDay[$iso] ?? []);
                usort($dayReservas, function (array $a, array $b): int {
                    return strcmp((string) ($a['hora_llegada'] ?? '99:99'), (string) ($b['hora_llegada'] ?? '99:99'));
                });
                $week[] = [
                    'fecha' => $iso,
                    'day' => (int) $cursor->format('j'),
                    'in_month' => $inRange,
                    'reservas' => $dayReservas,
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $casa = Casa::query()->find($casaId);
        $titleView = match ($viewMode) {
            'dia' => 'Día',
            'libro' => 'Mapa',
            default => 'Mensual',
        };
        $monthLabel = $desde->locale('es')->translatedFormat('F Y');

        $pdf = PDF::loadView('pdf.reservas_calendario', [
            'casaNombre' => $casa?->nombre ?? 'Casa',
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'selectedDate' => $selectedDate,
            'viewMode' => $viewMode,
            'titleView' => $titleView,
            'monthLabel' => $monthLabel,
            'rowsByDay' => $rowsByDay,
            'weeks' => $weeks,
            'habitaciones' => $habitaciones->values()->all(),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $name = sprintf('calendario_reservas_%s_%s.pdf', $viewMode, $desde->format('Ymd'));

        return $pdf->download($name);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return $request->validate([
            'habitacion_id' => [...$required, 'integer', Rule::exists('habitaciones', 'id')],
            'cliente_nombre' => [...$required, 'string', 'max:200'],
            'genero' => ['nullable', Rule::in([Reserva::GENERO_FEMENINO, Reserva::GENERO_MASCULINO])],
            'generos' => ['nullable', 'array'],
            'generos.*' => [Rule::in([Reserva::GENERO_FEMENINO, Reserva::GENERO_MASCULINO])],
            'agencia' => ['nullable', 'string', 'max:200'],
            'hora_llegada' => ['nullable', 'date_format:H:i'],
            'vuelo' => ['nullable', 'string', 'max:100'],
            'taxi_llegada' => ['sometimes', 'boolean'],
            'taxi_salida' => ['sometimes', 'boolean'],
            'cantidad_personas' => [...$required, 'integer', 'min:1'],
            'camas' => [...$required, 'array'],
            'camas.matrimonial' => ['nullable', 'integer', 'min:0'],
            'camas.twin' => ['nullable', 'integer', 'min:0'],
            'camas.single' => ['nullable', 'integer', 'min:0'],
            'cantidad_noches' => [...$required, 'integer', 'min:1'],
            'fecha_entrada' => [...$required, 'date'],
            'notas' => ['nullable', 'string'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{genero: string, generos: list<string>|null, camas: array<string, int>}
     */
    private function normalizeGenderAndBeds(Habitacion $habitacion, array $payload): array
    {
        $personas = (int) $payload['cantidad_personas'];
        $camas = Reserva::normalizeCamas(is_array($payload['camas'] ?? null) ? $payload['camas'] : null);
        $totalCamas = array_sum($camas);

        if ($totalCamas < 1) {
            throw ValidationException::withMessages([
                'camas' => ['Debe indicar al menos una cama.'],
            ]);
        }

        $plazas = Reserva::plazasFromCamas($camas);
        if ($plazas < $personas) {
            throw ValidationException::withMessages([
                'camas' => [sprintf(
                    'Las camas cubren %d plaza(s) y la reserva tiene %d persona(s).',
                    $plazas,
                    $personas
                )],
            ]);
        }

        if ($habitacion->isPrivada()) {
            $generos = array_values(array_filter(
                is_array($payload['generos'] ?? null) ? $payload['generos'] : [],
                fn ($g) => is_string($g) && $g !== ''
            ));

            if (count($generos) !== $personas) {
                throw ValidationException::withMessages([
                    'generos' => [sprintf(
                        'En habitaciones privadas debe indicar el género de cada persona (%d).',
                        $personas
                    )],
                ]);
            }

            foreach ($generos as $genero) {
                if (! in_array($genero, [Reserva::GENERO_FEMENINO, Reserva::GENERO_MASCULINO], true)) {
                    throw ValidationException::withMessages([
                        'generos' => ['Cada género debe ser femenino o masculino.'],
                    ]);
                }
            }

            return [
                'genero' => $generos[0],
                'generos' => $generos,
                'camas' => $camas,
            ];
        }

        $genero = $payload['genero'] ?? null;
        if (! is_string($genero) || ! in_array($genero, [Reserva::GENERO_FEMENINO, Reserva::GENERO_MASCULINO], true)) {
            throw ValidationException::withMessages([
                'genero' => ['En habitaciones compartidas el género es obligatorio.'],
            ]);
        }

        return [
            'genero' => $genero,
            'generos' => null,
            'camas' => $camas,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: Habitacion, 1: Carbon, 2: Carbon, 3: int}
     */
    private function resolveStay(array $payload, ?Reserva $existing = null): array
    {
        $habitacion = Habitacion::query()->findOrFail((int) $payload['habitacion_id']);

        $isSameRoom = $existing !== null && (int) $existing->habitacion_id === (int) $habitacion->id;
        if (! $habitacion->activo && ! $isSameRoom) {
            throw ValidationException::withMessages([
                'habitacion_id' => ['La habitación seleccionada está inactiva.'],
            ]);
        }

        $entrada = Carbon::parse((string) $payload['fecha_entrada'])->startOfDay();
        $noches = (int) $payload['cantidad_noches'];
        $salida = $this->availability->resolveSalida($entrada, $noches);

        return [$habitacion, $entrada, $salida, $noches];
    }
}
