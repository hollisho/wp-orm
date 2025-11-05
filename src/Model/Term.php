<?php

namespace WPOrm\Model;

/**
 * WordPress 分类/标签模型
 */
class Term extends Model
{
    protected static string $table = 'terms';
    protected static string $primaryKey = 'term_id';

    /**
     * 分类法关联
     */
    public function taxonomy()
    {
        return $this->hasOne(TermTaxonomy::class, 'term_id');
    }

    /**
     * 文章关联
     */
    public function posts()
    {
        return $this->belongsToMany(
            Post::class,
            'term_relationships',
            'term_taxonomy_id',
            'object_id'
        );
    }

    /**
     * 元数据关联
     */
    public function meta()
    {
        return $this->hasMany(TermMeta::class, 'term_id');
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
     * 作用域：指定分类法
     */
    public function scopeOfTaxonomy($query, string $taxonomy)
    {
        return $query->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
            ->where('term_taxonomy.taxonomy', $taxonomy);
    }
}
