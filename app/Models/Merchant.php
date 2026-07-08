<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'category', 'location', 'price_range', 'rating', 'review_count', 'delivery_minutes', 'delivery_fee', 'emoji', 'is_active'])]
class Merchant extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
