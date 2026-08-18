<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'soa_id',
    'order_id',
    'item_sku',
    'qty',
    'is_freebie',
    'order_status',
    'delivered_at',
    'shipped_at',
    'cod_amount',
    'shipping_cost',
    'store_name',
])]
class OrderLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_freebie' => 'boolean',
            'delivered_at' => 'datetime',
            'shipped_at' => 'datetime',
            'cod_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
        ];
    }
}
