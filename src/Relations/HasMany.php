<?php

namespace WPOrm\Relations;

use WPOrm\Builder\QueryBuilder;

/**
 * 一对多关联
 */
class HasMany extends Relation
{
    public function getQuery(): QueryBuilder
    {
        $query = $this->related::query();
        $this->addConstraints();
        return $query;
    }

    public function addConstraints(): void
    {
        // 添加外键约束
    }

    public function getResults()
    {
        $foreignValue = $this->parent->getAttribute($this->localKey);
        return $this->related::where($this->foreignKey, $foreignValue)->get();
    }
}
