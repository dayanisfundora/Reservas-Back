<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opcion extends Model
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
    protected $table = 'Opciones';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id_opc';

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
        'id_app',
        'desc_opc',
        'va_log_sheet',
        'id_parent',
        'historico',
        'desc_opc_api',
    ];

    /**
     * Get the application that owns the option.
     *
     * @return BelongsTo
     */
    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(Aplicacion::class, 'id_app', 'id_app');
    }

    /**
     * Get the parent option.
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Opcion::class, 'id_parent', 'id_opc');
    }

    /**
     * Get the child options.
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Opcion::class, 'id_parent', 'id_opc');
    }
}
