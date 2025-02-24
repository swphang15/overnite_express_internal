<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasFactory; // 确保这里正确引用

    protected $fillable = [
        'origin',
        'destination',
        'minimum_price',
        'minimum_weight',
        'additional_price_per_kg'
    ];
}
