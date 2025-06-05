<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clients'; // 明确指定表名
    protected $fillable = ['name', 'shipping_plan_id', "code"]; // 允许批量填充的字段
    protected $dates = ['deleted_at'];

    public function shippingPlan()
    {
        return $this->belongsTo(ShippingPlan::class, 'shipping_plan_id')->withTrashed();
    }
}
