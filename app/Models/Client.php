<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Client extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

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

    // // ⚠️ 如果用 setPasswordAttribute()，register 里不能用 Hash::make()
    // public function setPasswordAttribute($value)
    // {
    //     $this->attributes['password'] = bcrypt($value);
    // }

    // 自动设置 role
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            $client->role = str_contains($client->email, 'admin') ? 'admin' : 'client';
        });
    }
}
