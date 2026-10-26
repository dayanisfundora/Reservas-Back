<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\ActivityLog;

class Entity extends Model
{
    use HasFactory, LogsActivity, ActivityLog;

    protected $table = 'entity';
    
    protected $fillable = [
        'name',
        'abbr',
        'logo',
    ];

    protected $logAttributes = [
        'name',
        'abbr',
        'logo',
    ];
}
