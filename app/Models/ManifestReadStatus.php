<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestReadStatus extends Model
{
    protected $table = 'manifest_read_status';

    protected $fillable = [
        'user_id',
        'manifest_info_id',
        'read_at'
    ];

    protected $casts = [
        'read_at' => 'datetime'
    ];

    /**
     * 关联到用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联到清单信息
     */
    public function manifestInfo(): BelongsTo
    {
        return $this->belongsTo(ManifestInfo::class);
    }

    /**
     * 标记某个用户已读某个manifest
     */
    public static function markAsRead(int $userId, int $manifestInfoId): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'manifest_info_id' => $manifestInfoId
            ],
            [
                'read_at' => now()
            ]
        );
    }

    /**
     * 检查某个用户是否已读某个manifest
     */
    public static function isRead(int $userId, int $manifestInfoId): bool
    {
        return self::where('user_id', $userId)
            ->where('manifest_info_id', $manifestInfoId)
            ->exists();
    }

    /**
     * 获取某个用户的所有已读manifest ID
     */
    public static function getReadManifestIds(int $userId): array
    {
        return self::where('user_id', $userId)
            ->pluck('manifest_info_id')
            ->toArray();
    }
}
