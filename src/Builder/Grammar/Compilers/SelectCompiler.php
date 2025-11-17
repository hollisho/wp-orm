<?php
namespace WPOrm\Builder\Grammar\Compilers;

/**
 * SELECT 子句编译器
 */
class SelectCompiler
{
    /**
     * 编译 SELECT 子句
     */
    public function compile(array $columns): string
    {
        if (empty($columns)) {
            $columns = ['*'];
        }

        return 'SELECT ' . implode(', ', $columns);
    }
}
