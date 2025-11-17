<?php
namespace WPOrm\Builder\Grammar;

use WPOrm\Builder\QueryBuilder;
use WPOrm\Builder\Grammar\Compilers\SelectCompiler;
use WPOrm\Builder\Grammar\Compilers\WhereCompiler;
use WPOrm\Builder\Grammar\Compilers\JoinCompiler;

/**
 * MySQL 语法实现
 */
class MySQLGrammar implements Grammar
{
    protected SelectCompiler $selectCompiler;
    protected WhereCompiler $whereCompiler;
    protected JoinCompiler $joinCompiler;

    public function __construct()
    {
        $this->selectCompiler = new SelectCompiler();
        $this->whereCompiler = new WhereCompiler();
        $this->joinCompiler = new JoinCompiler();
    }

    /**
     * 编译 SELECT 查询
     */
    public function compileSelect(QueryBuilder $query): string
    {
        $components = array_filter([
            $this->selectCompiler->compile($query->getColumns()),
            $this->compileFrom($query),
            $this->compileJoins($query->getJoins()),
            $this->compileWheres($query->getWheres()),
            $this->compileGroups($query->getGroups()),
            $this->compileOrders($query->getOrders()),
            $this->compileLimit($query->getLimitValue(), $query->getOffsetValue()),
        ]);

        return implode(' ', $components);
    }

    /**
     * 编译 WHERE 子句
     */
    public function compileWheres(array $wheres): string
    {
        return $this->whereCompiler->compile($wheres);
    }

    /**
     * 编译 JOIN 子句
     */
    public function compileJoins(array $joins): string
    {
        return $this->joinCompiler->compile($joins);
    }

    /**
     * 编译 FROM 子句
     */
    public function compileFrom(QueryBuilder $query): string
    {
        $fromSubquery = $query->getFromSubquery();
        
        if ($fromSubquery !== null) {
            if ($fromSubquery instanceof QueryBuilder) {
                $subSql = $fromSubquery->toRawSql();
            } else {
                $subSql = $fromSubquery;
            }
            return "FROM ({$subSql}) as {$query->getFromSubqueryAlias()}";
        }

        return "FROM {$query->getFullTableName()}";
    }

    /**
     * 编译 GROUP BY 子句
     */
    public function compileGroups(array $groups): string
    {
        if (empty($groups)) {
            return '';
        }

        return 'GROUP BY ' . implode(', ', $groups);
    }

    /**
     * 编译 ORDER BY 子句
     */
    public function compileOrders(array $orders): string
    {
        if (empty($orders)) {
            return '';
        }

        $orderClauses = array_map(function ($order) {
            return "{$order['column']} {$order['direction']}";
        }, $orders);

        return 'ORDER BY ' . implode(', ', $orderClauses);
    }

    /**
     * 编译 LIMIT 子句
     */
    public function compileLimit(?int $limit, ?int $offset): string
    {
        $sql = '';

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        return trim($sql);
    }
}
