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
    protected ?QueryBuilder $query = null;

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
        if ($this->query === null) {
            $this->query = $this->related::query();
            $this->addConstraints($this->query);
        }
        return $this->query;
    }

    public function addConstraints(?QueryBuilder $query = null): void
    {
        if ($query === null) {
            return;
        }

        $parentKey = $this->parent->getKey();

        if ($parentKey) {
            $relatedTable = $this->related::getTable();
            $relatedPrimaryKey = $this->related::getPrimaryKey();
            $pivotTable = $this->pivotTable;

            // 标准的多对多关联：通过中间表连接
            // parent_table -> pivot_table -> related_table
            // join() 和 where() 方法会自动添加表前缀
            $query->join(
                $pivotTable,
                "{$relatedTable}.{$relatedPrimaryKey}",
                '=',
                "{$pivotTable}.{$this->relatedPivotKey}"
            );

            $query->where("{$pivotTable}.{$this->foreignPivotKey}", '=', $parentKey);
        }
    }

    public function getResults()
    {
        $query = $this->getQuery();
        return $query->get();
    }

    /**
     * 获取中间表名
     */
    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    /**
     * 获取外键（中间表中的父模型外键）
     */
    public function getForeignPivotKey(): string
    {
        return $this->foreignPivotKey;
    }

    /**
     * 获取关联键（中间表中的关联模型外键）
     */
    public function getRelatedPivotKey(): string
    {
        return $this->relatedPivotKey;
    }
}
