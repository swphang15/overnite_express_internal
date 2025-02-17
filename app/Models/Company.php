<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function sentManifests()
    {
        return $this->hasMany(Manifest::class, 'consignor_id');
    }

    public function receivedManifests()
    {
        return $this->hasMany(Manifest::class, 'consignee_id');
    }
}

