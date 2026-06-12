<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Customer extends Model
{
    protected $fillable = [
        'billable_id',
        'billable_type',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    protected $casts = [
        'billable_id' => 'integer',
        'trial_ends_at' => 'datetime',
    ];

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
