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
            $boolean = $isFirst ? '' : strtoupper($condition['boolean']) . ' ';
            $isFirst = false;

            if ($condition['type'] === 'column') {
                // 列与列的比较: table1.id = table2.id
                $onClauses[] = "{$boolean}{$condition['first']} {$condition['operator']} {$condition['second']}";
            } elseif ($condition['type'] === 'value') {
                // 列与值的比较: table.column = ?
                $onClauses[] = "{$boolean}{$condition['column']} {$condition['operator']} ?";
            }
        }

        $onClause = implode(' ', $onClauses);

        return "{$type} JOIN {$table} ON {$onClause}";
    }
}
