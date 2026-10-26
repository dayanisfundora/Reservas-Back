<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reserva extends Model
{
    use BelongsToEmpresa, HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_CONFIRMADA = 'confirmada';
    public const ESTADO_CANCELADA = 'cancelada';
    public const ESTADOS_ACTIVOS = [self::ESTADO_PENDIENTE, self::ESTADO_CONFIRMADA];

    public const GENERO_FEMENINO = 'femenino';
    public const GENERO_MASCULINO = 'masculino';

    public const CAMA_MATRIMONIAL = 'matrimonial';
    public const CAMA_TWIN = 'twin';
    public const CAMA_SINGLE = 'single';
    public const CAMA_TYPES = [self::CAMA_MATRIMONIAL, self::CAMA_TWIN, self::CAMA_SINGLE];
    public const CAMA_PLAZAS = [self::CAMA_MATRIMONIAL => 2, self::CAMA_TWIN => 2, self::CAMA_SINGLE => 1];

    protected $table = 'reservas';
    protected $fillable = [
        'empresa_id', 'casa_id', 'habitacion_id', 'cliente_nombre', 'genero', 'generos', 'agencia',
        'hora_llegada', 'vuelo', 'taxi_llegada', 'taxi_salida', 'cantidad_personas', 'camas',
        'cantidad_noches', 'fecha_entrada', 'fecha_salida', 'estado', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_personas' => 'integer',
            'cantidad_noches' => 'integer',
            'fecha_entrada' => 'date',
            'fecha_salida' => 'date',
            'camas' => 'array',
            'generos' => 'array',
            'taxi_llegada' => 'boolean',
            'taxi_salida' => 'boolean',
        ];
    }

    public function casa(): BelongsTo
    {
        return $this->belongsTo(Casa::class);
    }

    public function habitacion(): BelongsTo
    {
        return $this->belongsTo(Habitacion::class);
    }

    public function isConfirmada(): bool { return $this->estado === self::ESTADO_CONFIRMADA; }
    public function isPendiente(): bool { return $this->estado === self::ESTADO_PENDIENTE; }
    public function isActiva(): bool { return in_array($this->estado, self::ESTADOS_ACTIVOS, true); }

    public static function normalizeCamas(?array $camas): array
    {
        return [
            self::CAMA_MATRIMONIAL => max(0, (int) ($camas[self::CAMA_MATRIMONIAL] ?? 0)),
            self::CAMA_TWIN => max(0, (int) ($camas[self::CAMA_TWIN] ?? 0)),
            self::CAMA_SINGLE => max(0, (int) ($camas[self::CAMA_SINGLE] ?? 0)),
        ];
    }

    public static function plazasFromCamas(array $camas): int
    {
        $normalized = self::normalizeCamas($camas);
        $plazas = 0;
        foreach (self::CAMA_PLAZAS as $tipo => $porCama) {
            $plazas += $normalized[$tipo] * $porCama;
        }
        return $plazas;
    }

    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'casa_id' => $this->casa_id,
            'habitacion_id' => $this->habitacion_id,
            'cliente_nombre' => $this->cliente_nombre,
            'genero' => $this->genero,
            'generos' => is_array($this->generos) ? array_values($this->generos) : null,
            'agencia' => $this->agencia,
            'hora_llegada' => $this->hora_llegada ? (is_string($this->hora_llegada) ? substr($this->hora_llegada, 0, 5) : $this->hora_llegada) : null,
            'vuelo' => $this->vuelo,
            'taxi_llegada' => (bool) $this->taxi_llegada,
            'taxi_salida' => (bool) $this->taxi_salida,
            'cantidad_personas' => $this->cantidad_personas,
            'camas' => self::normalizeCamas(is_array($this->camas) ? $this->camas : null),
            'cantidad_noches' => $this->cantidad_noches,
            'fecha_entrada' => $this->fecha_entrada?->format('Y-m-d'),
            'fecha_salida' => $this->fecha_salida?->format('Y-m-d'),
            'estado' => $this->estado,
            'notas' => $this->notas,
            'habitacion' => $this->relationLoaded('habitacion') && $this->habitacion ? $this->habitacion->toSummaryArray() : null,
            'casa' => $this->relationLoaded('casa') && $this->casa ? $this->casa->toSummaryArray() : null,
        ];
    }
}
