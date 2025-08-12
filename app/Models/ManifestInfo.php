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
        'user_id', // 加上 user_id
    ];

    public function manifestLists(): HasMany
    {
        return $this->hasMany(ManifestList::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取此manifest的所有已读状态记录
     */
    public function readStatuses()
    {
        return $this->hasMany(ManifestReadStatus::class);
    }

    /**
     * 检查指定用户是否已读此manifest
     */
    public function isReadByUser(int $userId): bool
    {
        return ManifestReadStatus::isRead($userId, $this->id);
    }
}
