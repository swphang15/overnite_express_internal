<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manifest extends Model
{
    use HasFactory;

    protected $fillable = [
        'origin',
        'consignor_id',
        'consignee_name',
        'cn_no',
        'pcs',
        'kg',
        'gram',
        'remarks',
        'date',
        'awb_no',
        'to',
        'from',
        'flt',
        'manifest_no',
        'total_price', 
        'delivery_date',
    ];
    

    public function consignor()
    {
        return $this->belongsTo(Company::class, 'consignor_id');
    }


}

