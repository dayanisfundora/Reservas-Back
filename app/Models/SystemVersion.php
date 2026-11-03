<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Installed system version (Major.Minor.AppChange.DbChange).
 *
 * @property int $id
 * @property int $major
 * @property int $minor
 * @property int $app_change
 * @property int $db_change
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $applied_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class SystemVersion extends Model
{
    protected $table = 'system_versions';

    protected $fillable = [
        'major',
        'minor',
        'app_change',
        'db_change',
        'description',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    /**
     * Get the version as a string (e.g. 1.0.0.0).
     */
    public function getVersionString(): string
    {
        return $this->major . '.' . $this->minor . '.' . $this->app_change . '.' . $this->db_change;
    }
}
