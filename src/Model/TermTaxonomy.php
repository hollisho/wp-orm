<?php

namespace WPOrm\Model;

/**
 * WordPress 分类法模型
 */
class TermTaxonomy extends Model
{
    protected static string $table = 'term_taxonomy';
    protected static string $primaryKey = 'term_taxonomy_id';

    /**
     * 分类关联
     */
    public function term()
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /**
     * 父分类关联
     */
    public function parent()
    {
        return $this->belongsTo(TermTaxonomy::class, 'parent');
    }

    /**
     * 子分类关联
     */
    public function children()
    {
        return $this->hasMany(TermTaxonomy::class, 'parent');
    }
}
