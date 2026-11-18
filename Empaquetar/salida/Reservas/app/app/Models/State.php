<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\ActivityLog;

class State extends Model
{
    use HasFactory, LogsActivity, ActivityLog;

    protected $table = 'states';

    protected $fillable = [
        'description',
        'code',
        'state',
    ];

    protected $logAttributes = [
        'description',
        'code',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'boolean',
        ];
    }
}
