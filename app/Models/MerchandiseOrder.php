<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiseOrder extends Model
{
    protected $fillable = ['event_id', 'merchandise_variant_id', 'merchandise_product_id', 'public_token', 'order_number', 'buyer_name', 'buyer_phone', 'buyer_email', 'color', 'size', 'quantity', 'unit_price_tzs', 'total_tzs', 'fulfillment_method', 'status'];

    public function inventoryVariant(): BelongsTo
    {
        return $this->belongsTo(MerchandiseVariant::class, 'merchandise_variant_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MerchandiseProduct::class, 'merchandise_product_id');
    }
}
