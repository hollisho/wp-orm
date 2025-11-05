<?php

namespace WPOrm\Model;

/**
 * WordPress 用户元数据模型
 */
class UserMeta extends Model
{
    protected static string $table = 'usermeta';
    protected static string $primaryKey = 'umeta_id';
    protected static bool $isGlobalTable = true; // 用户元数据是全局表

    /**
     * 用户关联
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
