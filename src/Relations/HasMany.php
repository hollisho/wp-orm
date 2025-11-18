<?php

namespace WPOrm\Relations;

use WPOrm\Builder\QueryBuilder;

/**
 * 一对多关联
 */
class HasMany extends Relation
{
    protected ?QueryBuilder $query = null;

    public function getQuery(): QueryBuilder
    {
        if ($this->query === null) {
            $this->query = $this->related::query();
            $this->addConstraints();
        }
        return $this->query;
    }

    public function addConstraints(): void
    {
        if ($this->query !== null) {
            $localValue = $this->parent->getAttribute($this->localKey);
            if ($localValue !== null) {
                $this->query->where($this->foreignKey, $localValue);
            }
        }
    }

    public function getResults()
    {
        return $this->getQuery()->get();
    }
}
