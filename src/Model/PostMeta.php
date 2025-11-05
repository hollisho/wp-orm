<?php

namespace WPOrm\Model;

/**
 * WordPress 文章元数据模型
 */
class PostMeta extends Model
{
    protected static string $table = 'postmeta';
    protected static string $primaryKey = 'meta_id';

    /**
     * 文章关联
     */
    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
