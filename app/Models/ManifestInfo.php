<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; 
use Illuminate\Database\Eloquent\Relations\HasMany; 
class ManifestInfo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'manifest_infos';

    protected $fillable = [
        'date',
        'awb_no',
        'to',
        'from',
        'flt',
        'manifest_no',
    ];

    public function manifestLists(): HasMany
    {
        return $this->hasMany(ManifestList::class);
    }


}
