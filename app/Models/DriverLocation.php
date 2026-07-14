<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLocation extends Model
{
    protected $fillable = ['user_id', 'location', 'heading', 'speed', 'recorded_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
