<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'reference',
    'user_id',
    'payable_type',
    'payable_id',
    'purpose',
    'amount_fcfa',
    'method',
    'provider',
    'provider_reference',
    'status',
    'metadata',
    'paid_at',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'amount_fcfa' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
