<?php

namespace WPOrm\Model;

/**
 * WordPress 评论元数据模型
 */
class CommentMeta extends Model
{
    protected static string $table = 'commentmeta';
    protected static string $primaryKey = 'meta_id';

    /**
     * 评论关联
     */
    public function comment()
    {
        return $this->belongsTo(Comment::class, 'comment_id');
    }
}
