<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Casa;
use App\Models\Habitacion;
use App\Models\Reserva;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reservation reports. Only confirmed reservations count; a client belongs to the month of
 * its arrival and occupancy is room-nights ÷ (active rooms × days), the same rule as the calendar.
 */
class ReservaReporteService
{
    public const SIN_AGENCIA = 'Sin agencia (directo)';

    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * Clients grouped by agency for the reservations arriving in the selected months.
     *
     * @param list<int> $meses
     * @return array<string, mixed>
     */
    public function clientesPorAgencia(int $anio, array $meses, ?int $casaId, ?string $agencia): array
    {
        $periodo = $this->periodo($anio, $meses);
        $casas = $this->casas($casaId);

        $reservas = $this->reservasConfirmadas($casas->keys()->all())
            ->with(['habitacion', 'casa'])
            ->where('fecha_entrada', '>=', $periodo['desde']->toDateString())
            ->where('fecha_entrada', '<', $periodo['hasta']->toDateString())
            ->orderBy('fecha_entrada')
            ->orderBy('cliente_nombre')
            ->get();

        $filtro = $agencia !== null && trim($agencia) !== '' ? $this->agenciaKey($agencia) : null;
        $disponibles = [];
        $grupos = [];

        foreach ($reservas as $reserva) {
            if (! $this->llegaEnPeriodo($reserva, $periodo)) {
                continue;
            }

            [$key, $label] = $this->agenciaKeyLabel($reserva->agencia);
            $disponibles[$key] ??= $label;

            if ($filtro !== null && $key !== $filtro) {
                continue;
            }

            $grupos[$key] ??= ['agencia' => $label, 'reservas' => 0, 'personas' => 0, 'clientes' => []];
            $grupos[$key]['reservas']++;
            $grupos[$key]['personas'] += (int) $reserva->cantidad_personas;
            $grupos[$key]['clientes'][] = [
                'id' => $reserva->id,
                'cliente_nombre' => $reserva->cliente_nombre,
                'casa_nombre' => $reserva->casa?->nombre,
                'habitacion_nombre' => $reserva->habitacion?->nombre,
                'fecha_entrada' => $reserva->fecha_entrada->format('Y-m-d'),
                'fecha_salida' => $reserva->fecha_salida->format('Y-m-d'),
                'noches' => (int) $reserva->fecha_entrada->diffInDays($reserva->fecha_salida),
                'personas' => (int) $reserva->cantidad_personas,
            ];
        }

        $grupos = $this->ordenarGrupos($grupos);
        natcasesort($disponibles);

        return [
            'periodo' => $periodo['label'],
            'casa' => $this->casaLabel($casas, $casaId),
            'agencia_filtro' => $filtro !== null ? ($disponibles[$filtro] ?? trim((string) $agencia)) : null,
            'totales' => [
                'reservas' => array_sum(array_column($grupos, 'reservas')),
                'personas' => array_sum(array_column($grupos, 'personas')),
                'agencias' => count($grupos),
            ],
            'agencias' => $grupos,
            'agencias_disponibles' => array_values($disponibles),
        ];
    }

    /**
     * Per house: clients and occupancy contributed by each agency in the selected months
     * (an empty list means the whole year).
     *
     * @param list<int> $meses
     * @return array<string, mixed>
     */
    public function agenciasOcupacion(int $anio, array $meses, ?int $casaId): array
    {
        $periodo = $this->periodo($anio, $meses);
        $dias = $periodo['dias'];
        $casas = $this->casas($casaId);
        $habitacionesPorCasa = $this->habitacionesActivas($casas->keys()->all());

        $reservas = $this->reservasConfirmadas($casas->keys()->all())
            ->where('fecha_entrada', '<', $periodo['hasta']->toDateString())
            ->where('fecha_salida', '>', $periodo['desde']->toDateString())
            ->get();

        $resultado = [];
        $global = ['reservas' => 0, 'personas' => 0, 'noches' => 0, 'disponibles' => 0];

        foreach ($casas as $casa) {
            $habitacionIds = $habitacionesPorCasa->get($casa->id, collect())->pluck('id')->map(fn ($id) => (int) $id)->all();
            $disponibles = count($habitacionIds) * $dias;
            $agencias = [];
            $nochesCasa = [];

            foreach ($reservas->where('casa_id', $casa->id) as $reserva) {
                [$key, $label] = $this->agenciaKeyLabel($reserva->agencia);
                $agencias[$key] ??= ['agencia' => $label, 'reservas' => 0, 'personas' => 0, 'noches_set' => []];

                if ($this->llegaEnPeriodo($reserva, $periodo)) {
                    $agencias[$key]['reservas']++;
                    $agencias[$key]['personas'] += (int) $reserva->cantidad_personas;
                }

                if (! in_array((int) $reserva->habitacion_id, $habitacionIds, true)) {
                    continue;
                }

                foreach ($this->nochesEnPeriodo($reserva, $periodo) as $noche) {
                    $slot = $reserva->habitacion_id . '|' . $noche;
                    $agencias[$key]['noches_set'][$slot] = true;
                    $nochesCasa[$slot] = true;
                }
            }

            $filas = [];
            foreach ($agencias as $key => $fila) {
                $noches = count($fila['noches_set']);
                if ($fila['reservas'] === 0 && $noches === 0) {
                    continue;
                }
                $filas[$key] = [
                    'agencia' => $fila['agencia'],
                    'reservas' => $fila['reservas'],
                    'personas' => $fila['personas'],
                    'noches' => $noches,
                    'ocupacion' => $this->porcentaje($noches, $disponibles),
                ];
            }

            $totalCasa = [
                'reservas' => array_sum(array_column($filas, 'reservas')),
                'personas' => array_sum(array_column($filas, 'personas')),
                'noches' => count($nochesCasa),
                'disponibles' => $disponibles,
                'ocupacion' => $this->porcentaje(count($nochesCasa), $disponibles),
            ];

            foreach (['reservas', 'personas', 'noches', 'disponibles'] as $campo) {
                $global[$campo] += $totalCasa[$campo];
            }

            $resultado[] = [
                'casa_id' => $casa->id,
                'casa_nombre' => $casa->nombre,
                'habitaciones' => count($habitacionIds),
                'agencias' => $this->ordenarGrupos($filas),
                'total' => $totalCasa,
            ];
        }

        $global['ocupacion'] = $this->porcentaje($global['noches'], $global['disponibles']);

        return [
            'periodo' => $periodo['label'],
            'casa' => $this->casaLabel($casas, $casaId),
            'dias' => $dias,
            'totales' => $global,
            'casas' => $resultado,
        ];
    }

    /**
     * Month-by-month quantity and occupancy for a year.
     *
     * @return array<string, mixed>
     */
    public function resumenAnual(int $anio, ?int $casaId): array
    {
        $periodo = $this->periodo($anio, []);
        $casas = $this->casas($casaId);
        $habitacionIds = $this->habitacionesActivas($casas->keys()->all())->flatten(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $habitaciones = count($habitacionIds);

        $reservas = $this->reservasConfirmadas($casas->keys()->all())
            ->where('fecha_entrada', '<', $periodo['hasta']->toDateString())
            ->where('fecha_salida', '>', $periodo['desde']->toDateString())
            ->get();

        $meses = [];
        foreach (self::MESES as $numero => $nombre) {
            $meses[$numero] = [
                'mes' => $numero,
                'nombre' => $nombre,
                'reservas' => 0,
                'personas' => 0,
                'noches_set' => [],
                'disponibles' => $habitaciones * CarbonImmutable::create($anio, $numero, 1)->daysInMonth,
            ];
        }

        foreach ($reservas as $reserva) {
            if ($this->llegaEnPeriodo($reserva, $periodo)) {
                $mesLlegada = (int) $reserva->fecha_entrada->format('n');
                $meses[$mesLlegada]['reservas']++;
                $meses[$mesLlegada]['personas'] += (int) $reserva->cantidad_personas;
            }

            if (! in_array((int) $reserva->habitacion_id, $habitacionIds, true)) {
                continue;
            }

            foreach ($this->nochesEnPeriodo($reserva, $periodo) as $noche) {
                $meses[(int) substr($noche, 5, 2)]['noches_set'][$reserva->habitacion_id . '|' . $noche] = true;
            }
        }

        $filas = [];
        $total = ['reservas' => 0, 'personas' => 0, 'noches' => 0, 'disponibles' => 0];
        foreach ($meses as $mes) {
            $noches = count($mes['noches_set']);
            $filas[] = [
                'mes' => $mes['mes'],
                'nombre' => $mes['nombre'],
                'reservas' => $mes['reservas'],
                'personas' => $mes['personas'],
                'noches' => $noches,
                'disponibles' => $mes['disponibles'],
                'ocupacion' => $this->porcentaje($noches, $mes['disponibles']),
            ];
            $total['reservas'] += $mes['reservas'];
            $total['personas'] += $mes['personas'];
            $total['noches'] += $noches;
            $total['disponibles'] += $mes['disponibles'];
        }
        $total['ocupacion'] = $this->porcentaje($total['noches'], $total['disponibles']);

        return [
            'periodo' => 'Año ' . $anio,
            'casa' => $this->casaLabel($casas, $casaId),
            'habitaciones' => $habitaciones,
            'meses' => $filas,
            'totales' => $total,
        ];
    }

    /**
     * Selected months of a year (empty = all). `desde`/`hasta` bound the first and last month
     * (end exclusive); months in between that were not selected are excluded by `meses`.
     *
     * @param list<int> $meses
     * @return array{desde: CarbonImmutable, hasta: CarbonImmutable, meses: list<int>, dias: int, label: string}
     */
    private function periodo(int $anio, array $meses): array
    {
        $meses = array_values(array_unique(array_filter(
            array_map('intval', $meses),
            fn (int $mes) => $mes >= 1 && $mes <= 12,
        )));
        sort($meses);
        if ($meses === []) {
            $meses = range(1, 12);
        }

        $dias = 0;
        foreach ($meses as $mes) {
            $dias += CarbonImmutable::create($anio, $mes, 1)->daysInMonth;
        }

        return [
            'desde' => CarbonImmutable::create($anio, $meses[0], 1)->startOfDay(),
            'hasta' => CarbonImmutable::create($anio, $meses[count($meses) - 1], 1)->startOfDay()->addMonth(),
            'meses' => $meses,
            'dias' => $dias,
            'label' => $this->periodoLabel($anio, $meses),
        ];
    }

    /**
     * @param list<int> $meses Sorted, non-empty.
     */
    private function periodoLabel(int $anio, array $meses): string
    {
        $total = count($meses);

        if ($total === 12) {
            return 'Año ' . $anio;
        }

        if ($total === 1) {
            return self::MESES[$meses[0]] . ' ' . $anio;
        }

        if ($meses[$total - 1] - $meses[0] + 1 === $total) {
            return self::MESES[$meses[0]] . ' – ' . self::MESES[$meses[$total - 1]] . ' ' . $anio;
        }

        $nombres = array_map(fn (int $mes) => self::MESES[$mes], $meses);
        $ultimo = array_pop($nombres);

        return implode(', ', $nombres) . ' y ' . $ultimo . ' ' . $anio;
    }

    /**
     * Active houses of the current empresa (global scope), optionally a single one.
     *
     * @return Collection<int, Casa>
     */
    private function casas(?int $casaId): Collection
    {
        return Casa::query()
            ->where('activo', true)
            ->when($casaId !== null, fn (Builder $q) => $q->whereKey($casaId))
            ->orderBy('nombre')
            ->get()
            ->keyBy('id');
    }

    /**
     * @param list<int> $casaIds
     * @return Collection<int, Collection<int, Habitacion>>
     */
    private function habitacionesActivas(array $casaIds): Collection
    {
        return Habitacion::query()
            ->whereIn('casa_id', $casaIds)
            ->where('activo', true)
            ->get(['id', 'casa_id'])
            ->groupBy('casa_id');
    }

    /**
     * @param list<int> $casaIds
     * @return Builder<Reserva>
     */
    private function reservasConfirmadas(array $casaIds): Builder
    {
        return Reserva::query()
            ->where('estado', Reserva::ESTADO_CONFIRMADA)
            ->whereIn('casa_id', $casaIds);
    }

    /**
     * @param array{desde: CarbonImmutable, hasta: CarbonImmutable, meses: list<int>} $periodo
     */
    private function llegaEnPeriodo(Reserva $reserva, array $periodo): bool
    {
        $entrada = $reserva->fecha_entrada->format('Y-m-d');

        return $entrada >= $periodo['desde']->toDateString()
            && $entrada < $periodo['hasta']->toDateString()
            && in_array((int) $reserva->fecha_entrada->format('n'), $periodo['meses'], true);
    }

    /**
     * Nights (Y-m-d) of a reservation inside the selected months: entrada <= night < salida.
     *
     * @param array{desde: CarbonImmutable, hasta: CarbonImmutable, meses: list<int>} $periodo
     * @return list<string>
     */
    private function nochesEnPeriodo(Reserva $reserva, array $periodo): array
    {
        $inicio = CarbonImmutable::parse($reserva->fecha_entrada->format('Y-m-d'));
        $fin = CarbonImmutable::parse($reserva->fecha_salida->format('Y-m-d'));
        $inicio = $inicio->greaterThan($periodo['desde']) ? $inicio : $periodo['desde'];
        $fin = $fin->lessThan($periodo['hasta']) ? $fin : $periodo['hasta'];

        $noches = [];
        for ($dia = $inicio; $dia->lessThan($fin); $dia = $dia->addDay()) {
            if (in_array($dia->month, $periodo['meses'], true)) {
                $noches[] = $dia->toDateString();
            }
        }

        return $noches;
    }

    /**
     * @return array{0: string, 1: string} Grouping key and display label.
     */
    private function agenciaKeyLabel(?string $agencia): array
    {
        $label = $this->normalizarEspacios((string) $agencia);

        if ($label === '') {
            return ['', self::SIN_AGENCIA];
        }

        return [mb_strtolower($label), $label];
    }

    private function agenciaKey(string $agencia): string
    {
        $key = mb_strtolower($this->normalizarEspacios($agencia));

        return $key === mb_strtolower(self::SIN_AGENCIA) ? '' : $key;
    }

    private function normalizarEspacios(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    private function porcentaje(int $ocupadas, int $disponibles): float
    {
        if ($disponibles <= 0) {
            return 0.0;
        }

        return round(min(100.0, ($ocupadas / $disponibles) * 100.0), 1);
    }

    /**
     * Most reservations first, then alphabetical; "Sin agencia" goes last.
     *
     * @param array<string, array<string, mixed>> $grupos
     * @return list<array<string, mixed>>
     */
    private function ordenarGrupos(array $grupos): array
    {
        uksort($grupos, function (string $a, string $b) use ($grupos): int {
            if ($a === '' || $b === '') {
                return $a === '' ? 1 : -1;
            }

            return [$grupos[$b]['reservas'], $grupos[$a]['agencia']] <=> [$grupos[$a]['reservas'], $grupos[$b]['agencia']];
        });

        return array_values($grupos);
    }

    /**
     * @param Collection<int, Casa> $casas
     */
    private function casaLabel(Collection $casas, ?int $casaId): string
    {
        return $casaId !== null ? (string) ($casas->get($casaId)?->nombre ?? '') : 'Todas las casas';
    }
}
