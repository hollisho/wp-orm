<?php
namespace Dbout\WpOrm\Orm;

use Illuminate\Database\Query\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;

/**
 * Class Builder
 * @package Dbout\WpOrm\Orm
 */
class Builder extends EloquentBuilder {

    /**
     * Add an exists clause to the query.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function addWhereExistsQuery(EloquentBuilder $query, $boolean = 'and', $not = false) {
        
        $type = $not ? 'NotExists' : 'Exists';

        $this->wheres[] = compact('type', 'query', 'boolean');

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    /**
     * Makes "from" fetch from a subquery.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|string $query
     * @param  string  $as
     * @return $this
     */
    public function fromSub($query, $as)
    {
        $bindings = [];
        
        if ($query instanceof EloquentQueryBuilder) {
            $bindings = $query->getQuery()->getBindings();
            $query = $query->getQuery()->toSql();
        } elseif ($query instanceof EloquentBuilder) {
            $bindings = $query->getBindings();
            $query = $query->toSql();
        }

        return $this->fromRaw('(' . $query . ') as ' . $this->grammar->wrap($as), $bindings);
    }

    /**
     * Add a raw from clause to the query.
     *
     * @param  string  $expression
     * @param  array   $bindings
     * @return $this
     */
    public function fromRaw($expression, array $bindings = [])
    {
        $this->from = new \Illuminate\Database\Query\Expression($expression);

        if (!empty($bindings)) {
            $this->addBinding($bindings, 'where');
        }

        return $this;
    }

    /**
     * @return Database|\Illuminate\Database\ConnectionInterface
     */
    public function getConnection()
    {
        return Database::getInstance();
    }
}
