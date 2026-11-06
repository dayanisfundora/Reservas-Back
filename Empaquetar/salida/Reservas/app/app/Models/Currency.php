<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\ActivityLog;

class Currency extends Model
{
    use HasFactory, LogsActivity, ActivityLog;

    protected $table = 'currencies';

    protected $fillable = [
        'code',
        'description',
        'abbr',
        'rate',
        'base_currency',
        'convertible',
        'facturable',
        'state',
    ];

    protected $logAttributes = [
        'code',
        'description',
        'abbr',
        'rate',
        'base_currency',
        'convertible',
        'facturable',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'state' => 'boolean',
            'base_currency' => 'boolean',
            'convertible' => 'boolean',
            'facturable' => 'boolean',
        ];
    }
}
