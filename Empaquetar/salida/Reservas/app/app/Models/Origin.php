<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\ActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Origin extends Model
{
    use HasFactory, LogsActivity, ActivityLog;

    protected $table = 'origins';

    protected $fillable = [
        'code',
        'description',
        'state',
    ];

    protected $logAttributes = [
        'code',
        'description',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'boolean',
        ];
    }
}
