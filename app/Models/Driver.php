<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'phone', 'vehicle_type', 'vehicle_plate', 'rating', 'status', 'current_latitude', 'current_longitude'])]
class Driver extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
