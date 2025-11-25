<?php
namespace WPOrm\Builder\Grammar\Compilers;

use WPOrm\Builder\JoinClause;

/**
 * JOIN 子句编译器
 * 支持复杂的 JOIN 条件，包括 AND/OR 和值比较
 */
class JoinCompiler
{
    /**
     * 编译 JOIN 子句
     */
    public function compile(array $joins): string
    {
        if (empty($joins)) {
            return '';
        }

        $clauses = [];

        foreach ($joins as $join) {
            if ($join instanceof JoinClause) {
                $clauses[] = $this->compileJoinClause($join);
            } else {
                // 向后兼容：支持旧的数组格式
                $type = strtoupper($join['type']);
                $clauses[] = "{$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * 编译 JoinClause 对象
     */
    protected function compileJoinClause(JoinClause $join): string
    {
        $type = strtoupper($join->getType());
        $table = $join->getTable();
        $conditions = $join->getConditions();

        if (empty($conditions)) {
            return "{$type} JOIN {$table}";
        }

        $onClauses = [];
        $isFirst = true;

        foreach ($conditions as $condition) {
            if ($condition['type'] === 'column') {
                // 列与列的比较: table1.id = table2.id
                $clause = "{$condition['first']} {$condition['operator']} {$condition['second']}";
            } elseif ($condition['type'] === 'value') {
                // 列与值的比较: 直接嵌入值（需要转义）
                $value = $this->escapeValue($condition['value']);
                $clause = "{$condition['column']} {$condition['operator']} {$value}";
            } else {
                continue;
            }

            // 第一个条件不需要 boolean 前缀
            if (!$isFirst) {
                $clause = strtoupper($condition['boolean']) . ' ' . $clause;
            }
            $isFirst = false;

            $onClauses[] = $clause;
        }

        $onClause = implode(' ', $onClauses);

        return "{$type} JOIN {$table} ON {$onClause}";
    }

    /**
     * 转义值（用于 JOIN 条件）
     */
    protected function escapeValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        // 字符串需要转义并加引号
        global $wpdb;
        return "'" . $wpdb->_real_escape($value) . "'";
    }
}
