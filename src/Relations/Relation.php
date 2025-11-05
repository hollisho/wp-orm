<?php

namespace WPOrm\Relations;

use WPOrm\Builder\QueryBuilder;
use WPOrm\Model\Model;

/**
 * 关联基类
 */
abstract class Relation
{
    protected Model $parent;
    protected string $related;
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(Model $parent, string $related, string $foreignKey, string $localKey)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    /**
     * 获取查询构造器
     */
    abstract public function getQuery(): QueryBuilder;

    /**
     * 获取关联结果
     */
    abstract public function getResults();

    /**
     * 添加约束
     */
    abstract public function addConstraints(): void;
}
