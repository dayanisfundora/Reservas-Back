<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'type',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Relationship with ContractedService
     */
    public function contract()
    {
        return $this->belongsTo(ContractedService::class, 'contract_id');
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for contract expiration notifications
     */
    public function scopeContractExpirations($query)
    {
        return $query->where('type', 'contract_expiration');
    }
}
