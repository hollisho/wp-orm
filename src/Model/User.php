<?php

namespace WPOrm\Model;

/**
 * WordPress 用户模型
 */
class User extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'ID';
    protected static bool $isGlobalTable = true; // 用户表是全局表

    /**
     * 文章关联
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'post_author');
    }

    /**
     * 评论关联
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    /**
     * 元数据关联
     */
    public function meta()
    {
        return $this->hasMany(UserMeta::class, 'user_id');
    }

    /**
     * 获取元数据值
     */
    public function getMeta(string $key, $default = null)
    {
        $meta = $this->meta()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : $default;
    }

    /**
     * 设置元数据
     */
    public function setMeta(string $key, $value): bool
    {
        return update_user_meta($this->ID, $key, $value);
    }

    /**
     * 获取角色
     */
    public function getRoles(): array
    {
        $user = get_userdata($this->ID);
        return $user ? $user->roles : [];
    }

    /**
     * 检查是否有指定角色
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * 检查是否有指定权限
     */
    public function can(string $capability): bool
    {
        $user = get_userdata($this->ID);
        return $user ? $user->has_cap($capability) : false;
    }
}
