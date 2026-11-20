<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habitacion extends Model
{
    use BelongsToEmpresa, HasFactory;

    public const TIPO_PRIVADA = 'privada';
    public const TIPO_COMPARTIDA = 'compartida';

    public const COLOR_PALETTE = [
        '#0f766e', '#1d4ed8', '#7c3aed', '#db2777', '#ea580c', '#ca8a04',
        '#059669', '#0891b2', '#dc2626', '#4f46e5', '#9333ea', '#0e7490',
    ];

    protected $table = 'habitaciones';
    protected $fillable = ['empresa_id', 'casa_id', 'nombre', 'codigo', 'descripcion', 'tipo', 'capacidad', 'color', 'activo'];

    protected function casts(): array
    {
        return ['capacidad' => 'integer', 'activo' => 'boolean'];
    }

    public function casa(): BelongsTo
    {
        return $this->belongsTo(Casa::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function isPrivada(): bool
    {
        return $this->tipo === self::TIPO_PRIVADA;
    }

    public function isCompartida(): bool
    {
        return $this->tipo === self::TIPO_COMPARTIDA;
    }

    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'casa_id' => $this->casa_id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo,
            'capacidad' => $this->capacidad,
            'color' => $this->color ?: self::COLOR_PALETTE[0],
            'activo' => $this->activo,
            'casa' => $this->relationLoaded('casa') && $this->casa ? $this->casa->toSummaryArray() : null,
        ];
    }

    /**
     * First integer found in the room name (e.g. "Hab 10" → 10).
     */
    public static function sortNumberFromNombre(?string $nombre): ?int
    {
        if ($nombre === null || $nombre === '') {
            return null;
        }

        if (preg_match('/\d+/', $nombre, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    /**
     * Display order: numeric by name when present, otherwise creation order (id).
     *
     * @param  iterable<int, self>  $habitaciones
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function sortForDisplay(iterable $habitaciones): \Illuminate\Support\Collection
    {
        return collect($habitaciones)
            ->sort(function (self $a, self $b): int {
                $numA = self::sortNumberFromNombre($a->nombre);
                $numB = self::sortNumberFromNombre($b->nombre);

                $keyA = $numA ?? PHP_INT_MAX;
                $keyB = $numB ?? PHP_INT_MAX;

                if ($keyA !== $keyB) {
                    return $keyA <=> $keyB;
                }

                return ((int) $a->id) <=> ((int) $b->id);
            })
            ->values();
    }
}
