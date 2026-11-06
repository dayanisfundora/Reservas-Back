<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dato extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'ihsecurity';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'Datos';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id_datos';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_juego',
        'id_modulo',
        'base_datos',
        'origen_datos',
        'password',
        'login',
        'servidor',
        'descripcion',
    ];

    /**
     * Get juego for this dato.
     */
    public function juego_dato()
    {
        return $this->belongsTo(JuegoDato::class, 'id_juego', 'id_juego');
    }

    /**
     * Get accesos for this dato.
     */
    public function accesos()
    {
        return $this->hasMany(Acceso::class, 'id_datos', 'id_datos');
    }
}
