<?php
namespace WPOrm\Builder;

/**
 * JOIN 子句构建器
 * 支持复杂的 JOIN 条件，包括 AND/OR 条件
 */
class JoinClause
{
    protected string $type;
    protected string $table;
    protected array $conditions = [];
    protected array $bindings = [];

    public function __construct(string $type, string $table)
    {
        $this->type = $type;
        $this->table = $table;
    }

    /**
     * 添加 ON 条件
     */
    public function on(string $first, string $operator, string $second, string $boolean = 'and'): self
    {
        $this->conditions[] = [
            'type' => 'column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean
        ];

        return $this;
    }

    /**
     * 添加 OR ON 条件
     */
    public function orOn(string $first, string $operator, string $second): self
    {
        return $this->on($first, $operator, $second, 'or');
    }

    /**
     * 添加 WHERE 条件（用于 JOIN 中的值比较）
     */
    public function where(string $column, string $operator, $value, string $boolean = 'and'): self
    {
        $this->conditions[] = [
            'type' => 'value',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * 添加 OR WHERE 条件
     */
    public function orWhere(string $column, string $operator, $value): self
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * 获取 JOIN 类型
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 获取表名
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * 获取条件
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * 获取绑定参数
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
