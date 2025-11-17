<?php
namespace WPOrm\Builder\Grammar\Compilers;

/**
 * JOIN 子句编译器
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
            $type = strtoupper($join['type']);
            $clauses[] = "{$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        return implode(' ', $clauses);
    }
}
