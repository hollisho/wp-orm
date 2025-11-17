<?php
namespace WPOrm\Builder\Grammar\Compilers;

/**
 * WHERE 子句编译器
 * 负责将 WHERE 条件数组编译为 SQL 字符串
 */
class WhereCompiler
{
    /**
     * 编译 WHERE 子句
     */
    public function compile(array $wheres): string
    {
        if (empty($wheres)) {
            return '';
        }

        $clauses = [];

        foreach ($wheres as $index => $where) {
            $boolean = $index === 0 ? '' : " {$where['boolean']} ";

            $clause = $this->compileWhereType($where);

            if ($clause !== '') {
                $clauses[] = $boolean . $clause;
            }
        }

        return 'WHERE ' . implode('', $clauses);
    }

    /**
     * 根据类型编译 WHERE 条件
     */
    protected function compileWhereType(array $where): string
    {
        switch ($where['type']) {
            case 'basic':
                return $this->compileBasic($where);
            case 'in':
                return $this->compileIn($where);
            case 'not_in':
                return $this->compileNotIn($where);
            case 'in_sub':
                return $this->compileInSub($where);
            case 'not_in_sub':
                return $this->compileNotInSub($where);
            case 'exists':
                return $this->compileExists($where);
            case 'not_exists':
                return $this->compileNotExists($where);
            case 'null':
                return $this->compileNull($where);
            case 'not_null':
                return $this->compileNotNull($where);
            case 'nested':
                return $this->compileNested($where);
            default:
                return '';
        }
    }

    /**
     * 编译基础 WHERE 条件
     */
    protected function compileBasic(array $where): string
    {
        return "{$where['column']} {$where['operator']} %s";
    }

    /**
     * 编译 WHERE IN
     */
    protected function compileIn(array $where): string
    {
        $placeholders = implode(', ', array_fill(0, count($where['values']), '%s'));
        return "{$where['column']} IN ({$placeholders})";
    }

    /**
     * 编译 WHERE NOT IN
     */
    protected function compileNotIn(array $where): string
    {
        $placeholders = implode(', ', array_fill(0, count($where['values']), '%s'));
        return "{$where['column']} NOT IN ({$placeholders})";
    }

    /**
     * 编译 WHERE IN 子查询
     */
    protected function compileInSub(array $where): string
    {
        return "{$where['column']} IN ({$where['query']})";
    }

    /**
     * 编译 WHERE NOT IN 子查询
     */
    protected function compileNotInSub(array $where): string
    {
        return "{$where['column']} NOT IN ({$where['query']})";
    }

    /**
     * 编译 WHERE EXISTS
     */
    protected function compileExists(array $where): string
    {
        return "EXISTS ({$where['query']})";
    }

    /**
     * 编译 WHERE NOT EXISTS
     */
    protected function compileNotExists(array $where): string
    {
        return "NOT EXISTS ({$where['query']})";
    }

    /**
     * 编译 WHERE NULL
     */
    protected function compileNull(array $where): string
    {
        return "{$where['column']} IS NULL";
    }

    /**
     * 编译 WHERE NOT NULL
     */
    protected function compileNotNull(array $where): string
    {
        return "{$where['column']} IS NOT NULL";
    }

    /**
     * 编译嵌套 WHERE
     */
    protected function compileNested(array $where): string
    {
        $nestedSql = $this->compile($where['query']->getWheres());
        // 移除外层的 WHERE 关键字
        $nestedSql = preg_replace('/^WHERE\s+/', '', $nestedSql);
        return "({$nestedSql})";
    }
}
