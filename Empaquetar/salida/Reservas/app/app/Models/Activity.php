<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Activity model.
 */
class Activity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'description',
    ];

    /**
     * Get the budget indexes that have this activity.
     *
     * @return BelongsToMany
     */
    public function budget_indexes(): BelongsToMany
    {
        return $this->belongsToMany(BudgetIndex::class, 'budget_index_activities');
    }
}
