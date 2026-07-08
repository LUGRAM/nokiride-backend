<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['market_order_id', 'product_id', 'quantity', 'unit_price_fcfa', 'total_fcfa'])]
class MarketOrderItem extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketOrder::class, 'market_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
