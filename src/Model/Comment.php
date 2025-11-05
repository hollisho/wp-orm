<?php

namespace WPOrm\Model;

/**
 * WordPress 评论模型
 */
class Comment extends Model
{
    protected static string $table = 'comments';
    protected static string $primaryKey = 'comment_ID';

    /**
     * 文章关联
     */
    public function post()
    {
        return $this->belongsTo(Post::class, 'comment_post_ID');
    }

    /**
     * 作者关联
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 父评论关联
     */
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'comment_parent');
    }

    /**
     * 子评论关联
     */
    public function children()
    {
        return $this->hasMany(Comment::class, 'comment_parent');
    }

    /**
     * 元数据关联
     */
    public function meta()
    {
        return $this->hasMany(CommentMeta::class, 'comment_id');
    }

    /**
     * 作用域：已批准
     */
    public function scopeApproved($query)
    {
        return $query->where('comment_approved', '1');
    }
}
