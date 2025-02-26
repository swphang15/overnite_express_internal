<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'company_name',  // 从 Client 继承
        'name',          // 原 User 模型的字段
        'email',
        'password',
        'role',          // 从 Client 继承
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // 关系：一个用户可以发送多个 Manifest
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

        static::creating(function ($user) {
            if (strpos($user->email, 'admin') !== false) {
                $user->role = 'admin';
            } else {
                $user->role = 'client';
            }
        });
    }
}
