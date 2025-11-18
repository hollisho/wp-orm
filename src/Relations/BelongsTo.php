<?php

namespace WPOrm\Relations;

use WPOrm\Builder\QueryBuilder;

/**
 * 属于关联（反向一对多）
 */
class BelongsTo extends Relation
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
            $foreignValue = $this->parent->getAttribute($this->foreignKey);
            if ($foreignValue !== null) {
                $this->query->where($this->localKey, $foreignValue);
            }
        }
    }

    public function getResults()
    {
        return $this->getQuery()->first();
    }
}
