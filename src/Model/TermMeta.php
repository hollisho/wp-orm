<?php

namespace WPOrm\Model;

/**
 * WordPress 分类元数据模型
 */
class TermMeta extends Model
{
    protected static string $table = 'termmeta';
    protected static string $primaryKey = 'meta_id';

    /**
     * 分类关联
     */
    public function term()
    {
        return $this->belongsTo(Term::class, 'term_id');
    }
}
