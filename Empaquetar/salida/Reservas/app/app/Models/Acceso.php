<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acceso extends Model
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
    protected $table = 'Acceso';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id_acceso';

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
        'id_oper',
        'id_datos',
    ];

    /**
     * Get operador for this acceso.
     */
    public function operador()
    {
        return $this->belongsTo(Operador::class, 'id_oper', 'id_oper');
    }

    /**
     * Get dato for this acceso.
     */
    public function dato()
    {
        return $this->belongsTo(Dato::class, 'id_datos', 'id_datos');
    }
}
