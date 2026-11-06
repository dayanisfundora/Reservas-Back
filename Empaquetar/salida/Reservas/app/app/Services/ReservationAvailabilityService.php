<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Habitacion;
use App\Models\Reserva;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReservationAvailabilityService
{
    public function check(Habitacion $habitacion, Carbon $fechaEntrada, Carbon $fechaSalida, int $cantidadPersonas, ?int $excludeReservaId = null, ?string $genero = null): array
    {
        $capacidad = (int) $habitacion->capacidad;

        if ($fechaSalida->lte($fechaEntrada)) {
            return $this->result(false, $capacidad, 0, 0, 'La fecha de salida debe ser posterior a la de entrada.');
        }

        if ($cantidadPersonas < 1) {
            return $this->result(false, $capacidad, 0, $capacidad, 'La cantidad de personas debe ser al menos 1.');
        }

        if ($cantidadPersonas > $capacidad) {
            return $this->result(false, $capacidad, 0, $capacidad, sprintf('La habitación %s admite máximo %d persona(s).', $habitacion->nombre, $capacidad));
        }

        $overlapping = $this->overlappingReservations($habitacion->id, $fechaEntrada, $fechaSalida, $excludeReservaId);

        if ($habitacion->isPrivada()) {
            $disponible = $overlapping->isEmpty();

            return $this->result(
                $disponible,
                $capacidad,
                $disponible ? 0 : $capacidad,
                $disponible ? $capacidad : 0,
                $disponible ? null : sprintf('Habitación %s no disponible: ya está reservada en ese período.', $habitacion->nombre),
                $overlapping->map(fn (Reserva $r) => $r->toSummaryArray())->values()->all()
            );
        }

        if ($genero !== null && $genero !== '') {
            $genderConflicts = $overlapping->filter(fn (Reserva $r) => filled($r->genero) && $r->genero !== $genero);

            if ($genderConflicts->isNotEmpty()) {
                $ocupado = $this->peakOccupiedPersons($overlapping, $fechaEntrada, $fechaSalida);

                return $this->result(
                    false,
                    $capacidad,
                    $ocupado,
                    max(0, $capacidad - $ocupado),
                    sprintf('Habitación %s no disponible: en compartidas no se pueden mezclar géneros. Ya hay reserva(s) %s en ese período.', $habitacion->nombre, $genderConflicts->first()->genero === Reserva::GENERO_FEMENINO ? 'femenina(s)' : 'masculina(s)'),
                    $genderConflicts->map(fn (Reserva $r) => $r->toSummaryArray())->values()->all()
                );
            }
        }

        $peak = $this->peakOccupiedPersons($overlapping, $fechaEntrada, $fechaSalida);
        $restante = max(0, $capacidad - $peak);
        $disponible = ($peak + $cantidadPersonas) <= $capacidad;

        return $this->result(
            $disponible,
            $capacidad,
            $peak,
            $restante,
            $disponible ? null : sprintf('Habitación %s no disponible: capacidad restante %d persona(s) (solicitadas: %d).', $habitacion->nombre, $restante, $cantidadPersonas),
            $overlapping->map(fn (Reserva $r) => $r->toSummaryArray())->values()->all()
        );
    }

    public function assertAvailable(Habitacion $habitacion, Carbon $fechaEntrada, Carbon $fechaSalida, int $cantidadPersonas, ?int $excludeReservaId = null, ?string $genero = null): array
    {
        $result = $this->check($habitacion, $fechaEntrada, $fechaSalida, $cantidadPersonas, $excludeReservaId, $genero);

        if (! $result['disponible']) {
            throw ValidationException::withMessages(['disponibilidad' => [$result['mensaje'] ?? 'La habitación no está disponible.']]);
        }

        return $result;
    }

    public function calendar(int $casaId, Carbon $desde, Carbon $hasta): array
    {
        $habitaciones = Habitacion::query()->where('casa_id', $casaId)->where('activo', true)->orderBy('nombre')->get();

        $reservas = Reserva::query()
            ->with('habitacion')
            ->where('casa_id', $casaId)
            ->whereIn('estado', Reserva::ESTADOS_ACTIVOS)
            ->whereDate('fecha_entrada', '<', $hasta->copy()->addDay()->toDateString())
            ->whereDate('fecha_salida', '>', $desde->toDateString())
            ->orderBy('fecha_entrada')
            ->get()
            ->groupBy('habitacion_id');

        $days = [];
        foreach (CarbonPeriod::create($desde->copy()->startOfDay(), $hasta->copy()->startOfDay()) as $day) {
            $days[] = $day->format('Y-m-d');
        }

        $rows = [];
        foreach ($habitaciones as $habitacion) {
            $roomReservas = $reservas->get($habitacion->id, collect());
            $dayCells = [];

            foreach ($days as $dayStr) {
                $day = Carbon::parse($dayStr)->startOfDay();
                $active = $roomReservas->filter(fn (Reserva $r) => $r->fecha_entrada->lte($day) && $r->fecha_salida->gt($day));
                $ocupado = (int) $active->sum('cantidad_personas');
                $capacidad = (int) $habitacion->capacidad;

                if ($habitacion->isPrivada()) {
                    $estado = $active->isEmpty() ? 'libre' : 'lleno';
                    $ocupado = $active->isEmpty() ? 0 : $capacidad;
                } else {
                    $estado = match (true) {
                        $ocupado <= 0 => 'libre',
                        $ocupado >= $capacidad => 'lleno',
                        default => 'parcial',
                    };
                }

                $dayCells[] = [
                    'fecha' => $dayStr,
                    'estado' => $estado,
                    'ocupado' => $ocupado,
                    'restante' => max(0, $capacidad - $ocupado),
                    'capacidad' => $capacidad,
                    'reservas' => $active->map(fn (Reserva $r) => [
                        'id' => $r->id,
                        'cliente_nombre' => $r->cliente_nombre,
                        'genero' => $r->genero,
                        'cantidad_personas' => $r->cantidad_personas,
                        'hora_llegada' => $r->hora_llegada ? (is_string($r->hora_llegada) ? substr($r->hora_llegada, 0, 5) : $r->hora_llegada) : null,
                        'fecha_entrada' => $r->fecha_entrada->format('Y-m-d'),
                        'fecha_salida' => $r->fecha_salida->format('Y-m-d'),
                        'agencia' => $r->agencia,
                        'estado' => $r->estado,
                        'taxi_llegada' => (bool) $r->taxi_llegada,
                        'taxi_salida' => (bool) $r->taxi_salida,
                    ])->values()->all(),
                ];
            }

            $rows[] = [...$habitacion->toSummaryArray(), 'dias' => $dayCells];
        }

        return ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d'), 'habitaciones' => $rows];
    }

    /** @return Collection<int, Reserva> */
    public function overlappingReservations(int $habitacionId, Carbon $fechaEntrada, Carbon $fechaSalida, ?int $excludeReservaId = null): Collection
    {
        $query = Reserva::query()
            ->where('habitacion_id', $habitacionId)
            ->whereIn('estado', Reserva::ESTADOS_ACTIVOS)
            ->whereDate('fecha_entrada', '<', $fechaSalida->toDateString())
            ->whereDate('fecha_salida', '>', $fechaEntrada->toDateString());

        if ($excludeReservaId !== null) {
            $query->whereKeyNot($excludeReservaId);
        }

        return $query->orderBy('fecha_entrada')->get();
    }

    public function peakOccupiedPersons(Collection $reservas, Carbon $fechaEntrada, Carbon $fechaSalida): int
    {
        $peak = 0;
        $cursor = $fechaEntrada->copy()->startOfDay();
        $end = $fechaSalida->copy()->startOfDay();

        while ($cursor->lt($end)) {
            $dayOccupied = $reservas->filter(fn (Reserva $r) => $r->fecha_entrada->lte($cursor) && $r->fecha_salida->gt($cursor))->sum('cantidad_personas');
            $peak = max($peak, (int) $dayOccupied);
            $cursor->addDay();
        }

        return $peak;
    }

    public function resolveSalida(Carbon $fechaEntrada, int $cantidadNoches): Carbon
    {
        return $fechaEntrada->copy()->addDays($cantidadNoches);
    }

    private function result(bool $disponible, int $capacidad, int $ocupado, int $restante, ?string $mensaje, array $conflictos = []): array
    {
        return compact('disponible', 'capacidad', 'ocupado', 'restante', 'mensaje', 'conflictos');
    }
}
