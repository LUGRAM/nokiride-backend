<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['reference', 'user_id', 'driver_id', 'pickup_place_id', 'dropoff_place_id', 'pickup_address', 'dropoff_address', 'pickup_latitude', 'pickup_longitude', 'dropoff_latitude', 'dropoff_longitude', 'service_type', 'distance_km', 'price_fcfa', 'estimated_minutes', 'status', 'completed_at'])]
class Trip extends Model
{
    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function pickupPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'pickup_place_id');
    }

    public function dropoffPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'dropoff_place_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
