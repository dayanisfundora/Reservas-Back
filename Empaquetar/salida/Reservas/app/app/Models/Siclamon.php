<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Siclamon Model
 * 
 * Represents the SICLAMON table from the ihsecurity database.
 * Contains currency information including code, description, rate, commission, decimals,
 * and boolean flags for convertible and billable status.
 * 
 * @property string $cod_mone Currency code
 * @property string $desc_mone Currency description
 * @property string $tasa Exchange rate
 * @property string $comision Commission
 * @property int $decimales Number of decimal places
 * @property bool $convertible Whether the currency is convertible
 * @property bool $facturable Whether the currency is billable
 */
class Siclamon extends Model
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
    protected $table = 'SICLAMON';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'cod_mone';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

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
        'id_mone',
        'cod_mone',
        'desc_mone',
        'tasa',
        'comision',
        'decimales',
        'convertible',
        'facturable',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'decimales' => 'integer',
        'convertible' => 'boolean',
        'facturable' => 'boolean',
    ];
}
