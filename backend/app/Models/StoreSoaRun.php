<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'soa_id',
    'store_name',
    'net_remittance',
    'total_cogs',
    'total_ds_fee',
    'net_pay',
])]
class StoreSoaRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'net_remittance' => 'decimal:2',
            'total_cogs' => 'decimal:2',
            'total_ds_fee' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }
}
