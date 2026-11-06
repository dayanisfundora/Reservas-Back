<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'nombre_corto', 'nit', 'logo', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function casas(): HasMany
    {
        return $this->hasMany(Casa::class);
    }

    public function habitaciones(): HasMany
    {
        return $this->hasMany(Habitacion::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'nombre_corto' => $this->nombre_corto ?? $this->nombre,
            'nit' => $this->nit,
            'logo' => $this->logo,
            'activo' => $this->activo,
        ];
    }
}
