<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'shipping_rates'; // 指定表名
    protected $fillable = [
        'shipping_plan_id', 
        'origin', 
        'destination',
        'minimum_price', 
        'minimum_weight',
        'additional_price_per_kg'
    ];

    // 关联 ShippingPlan
    public function shippingPlan()
    {
        return $this->belongsTo(ShippingPlan::class);
    }
}
