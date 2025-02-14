<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'origin', 'consignor', 'consignee', 'cn_no', 'pcs',
        'kg', 'gram', 'remarks', 'date', 'awb_no', 'to',
        'from', 'flt', 'manifest_no'
    ];
}
