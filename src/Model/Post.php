<?php

namespace WPOrm\Model;

/**
 * WordPress 文章模型
 */
class Post extends Model
{
    protected static string $table = 'posts';
    protected static string $primaryKey = 'ID';

    /**
     * 作者关联
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'post_author');
    }

    /**
     * 评论关联
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'comment_post_ID');
    }

    /**
     * 分类关联
     */
    public function categories()
    {
        return $this->belongsToMany(
            Term::class,
            'term_relationships',
            'object_id',
            'term_taxonomy_id'
        )->where('taxonomy', 'category');
    }

    /**
     * 标签关联
     */
    public function tags()
    {
        return $this->belongsToMany(
            Term::class,
            'term_relationships',
            'object_id',
            'term_taxonomy_id'
        )->where('taxonomy', 'post_tag');
    }

    /**
     * 元数据关联
     */
    public function meta()
    {
        return $this->hasMany(PostMeta::class, 'post_id');
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
        return update_post_meta($this->ID, $key, $value);
    }

    /**
     * 作用域：已发布
     */
    public function scopePublished($query)
    {
        return $query->where('post_status', 'publish');
    }

    /**
     * 作用域：指定类型
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('post_type', $type);
    }
}
