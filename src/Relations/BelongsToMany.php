<?php

namespace WPOrm\Relations;

use WPOrm\Builder\QueryBuilder;

/**
 * 多对多关联
 */
class BelongsToMany extends Relation
{
    protected string $pivotTable;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;

    public function __construct(
        $parent,
        string $related,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey
    ) {
        $this->parent = $parent;
        $this->related = $related;
        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
    }

    public function getQuery(): QueryBuilder
    {
        $query = $this->related::query();
        $this->addConstraints();
        return $query;
    }

    public function addConstraints(): void
    {
        // 添加中间表约束
    }

    public function getResults()
    {
        // 简化实现，实际需要通过中间表查询
        return $this->related::query()->get();
    }
}
