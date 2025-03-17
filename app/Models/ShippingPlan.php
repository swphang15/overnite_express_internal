<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'shipping_plans'; // 明确指定表名
    protected $fillable = ['plan_name']; // 允许批量填充的字段
    
    public function shippingRates()
    {
        return $this->hasMany(ShippingRate::class);
    }

}
