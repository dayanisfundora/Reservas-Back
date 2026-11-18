<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aplicacion extends Model
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
    protected $table = 'Aplicaciones';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id_app';

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
        'id_modulo',
        'desc_app',
    ];

    /**
     * Get the module that owns the application.
     *
     * @return BelongsTo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'id_modulo', 'id_modulo');
    }

    /**
     * Get the options for this application.
     *
     * @return HasMany
     */
    public function opciones(): HasMany
    {
        return $this->hasMany(Opcion::class, 'id_app', 'id_app');
    }
}
