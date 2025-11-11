<?php

namespace WPOrm\Relations;

use WPOrm\Builder\QueryBuilder;

/**
 * 属于关联（反向一对多）
 */
class BelongsTo extends Relation
{
    public function getQuery(): QueryBuilder
    {
        $query = $this->related::query();
        $this->addConstraints();
        return $query;
    }

    public function addConstraints(): void
    {
        // 添加约束
    }

    public function getResults()
    {
        $foreignValue = $this->parent->getAttribute($this->foreignKey);
        return $this->related::query()->where($this->localKey, $foreignValue)->first();
    }
}
