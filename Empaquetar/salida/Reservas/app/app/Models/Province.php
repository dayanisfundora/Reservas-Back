<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\ActivityLog;

class Province extends Model
{
    use HasFactory, LogsActivity, ActivityLog;

    protected $table = 'provinces';

    protected $fillable = [
        'description',
        'code',
        'abbr',
        'state',
    ];

    protected $logAttributes = [
        'description',
        'code',
        'abbr',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'boolean',
        ];
    }

    public function customerSuppliers()
    {
        return $this->hasMany(CustomerSupplier::class, 'province_id');
    }

    public function brigades()
    {
        return $this->hasMany(Brigade::class, 'brigade_id');
    }
}
