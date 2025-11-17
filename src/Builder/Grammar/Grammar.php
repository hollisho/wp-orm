<?php
namespace WPOrm\Builder\Grammar;


use WPOrm\Builder\QueryBuilder;

/**
 * SQL 语法接口
 * 定义 SQL 生成的标准接口，支持多种数据库
 */
interface Grammar
{
    /**
     * 编译 SELECT 查询
     */
    public function compileSelect(QueryBuilder $query): string;

    /**
     * 编译 WHERE 子句
     */
    public function compileWheres(array $wheres): string;

    /**
     * 编译 JOIN 子句
     */
    public function compileJoins(array $joins): string;

    /**
     * 编译 FROM 子句
     */
    public function compileFrom(QueryBuilder $query): string;

    /**
     * 编译 GROUP BY 子句
     */
    public function compileGroups(array $groups): string;

    /**
     * 编译 ORDER BY 子句
     */
    public function compileOrders(array $orders): string;

    /**
     * 编译 LIMIT 子句
     */
    public function compileLimit(?int $limit, ?int $offset): string;
}
