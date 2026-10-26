<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JuegoDato extends Model
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
    protected $table = 'Juegos_Datos';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id_juego';

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
        'descripcion',
    ];

    /**
     * Get datos for this juego.
     */
    public function datos()
    {
        return $this->hasMany(Dato::class, 'id_juego', 'id_juego');
    }
}
