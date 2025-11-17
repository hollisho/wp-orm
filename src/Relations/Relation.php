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

    /**
     * 获取父模型
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * 获取关联模型类名
     */
    public function getRelated(): string
    {
        return $this->related;
    }

    /**
     * 获取外键
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * 获取本地键
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**
     * 魔术方法：将方法调用转发给 QueryBuilder
     */
    public function __call($method, $parameters)
    {
        $result = $this->getQuery()->$method(...$parameters);

        // 如果返回的是 QueryBuilder 实例（链式调用），返回 Relation 实例以保持链式调用
        if ($result instanceof QueryBuilder) {
            return $this;
        }

        // 否则返回实际结果（如 get(), first() 等）
        return $result;
    }
}
