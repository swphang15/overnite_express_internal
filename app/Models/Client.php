<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
    public function sentManifests()
    {
        return $this->hasMany(Manifest::class, 'consignor_id');
    }

    // 自动加密密码
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }

    // 自动设置 role
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            // 如果 email 里包含 "admin"，就设置 role = admin，否则默认 client
            if (strpos($client->email, 'admin') !== false) {
                $client->role = 'admin';
            } else {
                $client->role = 'client';
            }
        });
    }
}
