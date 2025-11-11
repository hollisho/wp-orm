<?php

namespace WPOrm\Builder;

use WPOrm\Database\Connection;
use WPOrm\Database\ConnectionManager;
use WPOrm\Collection\Collection;

/**
 * 查询构造器
 * 支持链式操作
 */
class QueryBuilder
{
    protected string $modelClass;
    protected Connection $connection;
    protected string $table;
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected array $columns = ['*'];
    protected array $joins = [];
    protected array $with = [];
    protected ?int $siteId = null;
    protected bool $useGlobalTable = false;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->connection = ConnectionManager::connection();
        $this->table = $modelClass::getTable();
    }

    /**
     * 选择列
     */
    public function select(...$columns): self
    {
        $this->columns = is_array($columns[0]) ? $columns[0] : $columns;
        return $this;
    }

    /**
     * Where 条件
     */
    public function where($column, $operator = null, $value = null): self
    {
        // 支持闭包
        if ($column instanceof \Closure) {
            $query = new static($this->modelClass);
            $column($query);
            $this->wheres[] = ['type' => 'nested', 'query' => $query];
            return $this;
        }

        // 支持数组
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val);
            }
            return $this;
        }

        // 标准 where
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Or Where 条件
     */
    public function orWhere($column, $operator = null, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Where In
     */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * Where Not In
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * Where Null
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * Where Not Null
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * Order By
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) === 'desc' ? 'DESC' : 'ASC'
        ];

        return $this;
    }

    /**
     * Limit
     */
    public function limit(int $value): self
    {
        $this->limitValue = $value;
        return $this;
    }

    /**
     * Offset
     */
    public function offset(int $value): self
    {
        $this->offsetValue = $value;
        return $this;
    }

    /**
     * Take (alias for limit)
     */
    public function take(int $value): self
    {
        return $this->limit($value);
    }

    /**
     * Skip (alias for offset)
     */
    public function skip(int $value): self
    {
        return $this->offset($value);
    }

    /**
     * Join
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Left Join
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * 预加载关联
     */
    public function with(...$relations): self
    {
        $this->with = is_array($relations[0]) ? $relations[0] : $relations;
        return $this;
    }

    /**
     * 指定站点（多站点模式）
     */
    public function onSite(int $siteId): self
    {
        $this->siteId = $siteId;
        ConnectionManager::setSiteId($siteId);
        return $this;
    }

    /**
     * 使用全局表
     */
    public function global(): self
    {
        $this->useGlobalTable = true;
        return $this;
    }

    /**
     * 获取所有结果
     */
    public function get(): Collection
    {
        $sql = $this->toSql();
        $results = $this->connection->select($sql, $this->bindings);

        $models = array_map(function ($row) {
            return $this->modelClass::newInstance($row);
        }, $results);

        $collection = new Collection($models);

        // 加载关联
        if (!empty($this->with)) {
            $collection = $this->eagerLoadRelations($collection);
        }

        return $collection;
    }

    /**
     * 获取第一条结果
     */
    public function first(): ?object
    {
        $results = $this->limit(1)->get();
        return $results->first();
    }

    /**
     * 根据 ID 查找
     */
    public function find($id): ?object
    {
        $primaryKey = $this->modelClass::getPrimaryKey();
        return $this->where($primaryKey, $id)->first();
    }

    /**
     * 获取数量
     */
    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) as count'];

        $sql = $this->toSql();
        $result = $this->connection->select($sql, $this->bindings);

        $this->columns = $originalColumns;

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * 检查是否存在
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * 分页
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $items = $this->limit($perPage)->offset($offset)->get();

        return [
            'data' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * 转换为 SQL
     */
    public function toSql(): string
    {
        $table = $this->getFullTableName();
        $columns = implode(', ', $this->columns);

        $sql = "SELECT {$columns} FROM {$table}";

        // Joins
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $type = strtoupper($join['type']);
                $sql .= " {$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        // Where
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        // Order By
        if (!empty($this->orders)) {
            $orderClauses = array_map(function ($order) {
                return "{$order['column']} {$order['direction']}";
            }, $this->orders);
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        // Limit
        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        // Offset
        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    /**
     * 转换为 SQL，并绑定参数
     * @return string
     */
    public function toRawSql(): string
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();

        return sprintf($sql, ...$bindings);
    }

    /**
     * 编译 Where 子句
     */
    protected function compileWheres(): string
    {
        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : " {$where['boolean']} ";

            switch ($where['type']) {
                case 'basic':
                    $clauses[] = $boolean . "{$where['column']} {$where['operator']} %s";
                    break;
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '%s'));
                    $clauses[] = $boolean . "{$where['column']} IN ({$placeholders})";
                    break;
                case 'not_in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '%s'));
                    $clauses[] = $boolean . "{$where['column']} NOT IN ({$placeholders})";
                    break;
                case 'null':
                    $clauses[] = $boolean . "{$where['column']} IS NULL";
                    break;
                case 'not_null':
                    $clauses[] = $boolean . "{$where['column']} IS NOT NULL";
                    break;
                case 'nested':
                    $nestedSql = $where['query']->compileWheres();
                    $clauses[] = $boolean . "({$nestedSql})";
                    break;
            }
        }

        return implode('', $clauses);
    }

    /**
     * 获取完整表名
     */
    protected function getFullTableName(): string
    {
        return $this->connection->getTableName($this->table, $this->useGlobalTable);
    }

    /**
     * 预加载关联
     */
    protected function eagerLoadRelations(Collection $collection): Collection
    {
        foreach ($this->with as $relation) {
            $collection = $this->loadRelation($collection, $relation);
        }

        return $collection;
    }

    /**
     * 加载关联
     */
    protected function loadRelation(Collection $collection, string $relation): Collection
    {
        // 实现关联加载逻辑
        // 这里简化处理，实际需要根据关联类型处理
        return $collection;
    }

    /**
     * 获取绑定参数
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
