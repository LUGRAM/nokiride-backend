<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['reference', 'user_id', 'driver_id', 'pickup_address', 'dropoff_address', 'recipient_name', 'recipient_phone', 'parcel_size', 'parcel_note', 'distance_km', 'price_fcfa', 'estimated_minutes', 'status', 'delivered_at'])]
class Delivery extends Model
{
    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'delivered_at' => 'datetime',
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

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
