<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modulo extends Model
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
    protected $table = 'Modulo';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id_modulo';

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
        'desc_modulo',
        'conexion',
        'base_datos',
    ];

    /**
     * Get the applications for this module.
     *
     * @return HasMany
     */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(Aplicacion::class, 'id_modulo', 'id_modulo');
    }
}
