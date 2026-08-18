<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'billing_start',
    'billing_end',
    'generated_by',
    'timestamp',
    'store_name',
    'seller_name',
])]
class SoaRun extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_start' => 'date',
            'billing_end' => 'date',
            'timestamp' => 'datetime',
        ];
    }
}
