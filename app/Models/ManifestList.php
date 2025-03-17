<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; 
class ManifestList extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'manifest_lists';

    protected $fillable = [
        'manifest_info_id',
        'consignor_id',
        'consignee_name',
        'cn_no',
        'pcs',
        'kg',
        'gram',
        'remarks',
        'total_price',
        'discount',
        'origin'
    ];

    public function manifestInfo()
    {
     
        return $this->belongsTo(ManifestInfo::class, 'manifest_info_id');
    }

    public function consignor()
    {
        return $this->belongsTo(Client::class, 'consignor_id');
    }
  public function client()
{
    return $this->belongsTo(Client::class, 'consignor_id');
}

}
