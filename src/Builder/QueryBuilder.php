<?php

namespace WPOrm\Builder;

use WPOrm\Relations\BelongsTo;
use WPOrm\Relations\BelongsToMany;
use WPOrm\Relations\HasMany;
use WPOrm\Relations\HasOne;
use WPOrm\Relations\Relation;
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
    protected array $groups = [];
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
     * Group By
     */
    public function groupBy(...$columns): self
    {
        $columns = is_array($columns[0]) ? $columns[0] : $columns;
        $this->groups = array_merge($this->groups, $columns);
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
     *
     * 支持多种格式：
     * 1. 简单预加载: with('author', 'comments')
     * 2. 数组格式: with(['author', 'comments'])
     * 3. 条件预加载: with(['comments' => function($query) { $query->where('status', 'approved'); }])
     * 4. 混合格式: with('author', ['comments' => function($query) { ... }])
     */
    public function with(...$relations): self
    {
        // 处理第一个参数是数组的情况
        if (count($relations) === 1 && is_array($relations[0])) {
            $relations = $relations[0];
        }

        // 解析关联配置
        foreach ($relations as $key => $value) {
            if (is_numeric($key)) {
                // 简单字符串关联: 'author'
                $this->with[$value] = null;
            } else {
                // 带约束的关联: 'comments' => function($query) { ... }
                $this->with[$key] = $value;
            }
        }

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
            'items' => $items,
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

        // Group By
        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
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
        foreach ($this->with as $relation => $constraints) {
            $collection = $this->loadRelation($collection, $relation, $constraints);
        }

        return $collection;
    }

    /**
     * 加载关联
     *
     * @param Collection $collection 模型集合
     * @param string $relation 关联名称
     * @param \Closure|null $constraints 查询约束闭包
     */
    protected function loadRelation(Collection $collection, string $relation, ?\Closure $constraints = null): Collection
    {
        if ($collection->isEmpty()) {
            return $collection;
        }

        // 支持嵌套关联，如 'posts.comments'
        if (strpos($relation, '.') !== false) {
            return $this->loadNestedRelation($collection, $relation, $constraints);
        }

        // 获取第一个模型实例来检查关联方法
        $firstModel = $collection->first();

        if (!method_exists($firstModel, $relation)) {
            return $collection;
        }

        // 调用关联方法获取关联对象
        $relationInstance = $firstModel->$relation();

        if (!$relationInstance instanceof Relation) {
            return $collection;
        }

        // 根据关联类型执行预加载
        $relationClass = get_class($relationInstance);

        switch ($relationClass) {
            case HasOne::class:
                return $this->eagerLoadHasOne($collection, $relation, $relationInstance, $constraints);

            case HasMany::class:
                return $this->eagerLoadHasMany($collection, $relation, $relationInstance, $constraints);

            case BelongsTo::class:
                return $this->eagerLoadBelongsTo($collection, $relation, $relationInstance, $constraints);

            case BelongsToMany::class:
                return $this->eagerLoadBelongsToMany($collection, $relation, $relationInstance, $constraints);

            default:
                return $collection;
        }
    }

    /**
     * 预加载嵌套关联
     */
    protected function loadNestedRelation(Collection $collection, string $relation, ?\Closure $constraints = null): Collection
    {
        $relations = explode('.', $relation);
        $firstRelation = array_shift($relations);

        // 加载第一层关联（只有最后一层才应用约束）
        $firstConstraints = empty($relations) ? $constraints : null;
        $collection = $this->loadRelation($collection, $firstRelation, $firstConstraints);

        // 递归加载嵌套关联
        if (!empty($relations)) {
            $nestedRelation = implode('.', $relations);

            foreach ($collection as $model) {
                $relatedModels = $model->getAttribute($firstRelation);

                if ($relatedModels instanceof Collection && !$relatedModels->isEmpty()) {
                    $firstRelatedModel = $relatedModels->first();
                    $relatedQuery = new static(get_class($firstRelatedModel));
                    $relatedQuery->loadRelation($relatedModels, $nestedRelation, $constraints);
                } elseif ($relatedModels && is_object($relatedModels)) {
                    $relatedCollection = new Collection([$relatedModels]);
                    $relatedQuery = new static(get_class($relatedModels));
                    $relatedQuery->loadRelation($relatedCollection, $nestedRelation, $constraints);
                }
            }
        }

        return $collection;
    }

    /**
     * 预加载 HasOne 关联
     */
    protected function eagerLoadHasOne(Collection $collection, string $relation, $relationInstance, ?\Closure $constraints = null): Collection
    {
        $localKey = $relationInstance->getLocalKey();
        $foreignKey = $relationInstance->getForeignKey();
        $relatedClass = $relationInstance->getRelated();

        // 获取所有父模型的本地键值
        $localKeys = $collection->pluck($localKey)->unique()->filter()->all();

        if (empty($localKeys)) {
            return $collection;
        }

        // 批量查询关联模型
        $query = $relatedClass::query()->whereIn($foreignKey, $localKeys);

        // 应用自定义约束
        if ($constraints !== null) {
            $constraints($query);
        }

        $relatedModels = $query->get();

        // 按外键分组
        $relatedByKey = $relatedModels->keyBy($foreignKey);

        // 将关联模型附加到父模型
        foreach ($collection as $model) {
            $localKeyValue = $model->getAttribute($localKey);
            $related = $relatedByKey->get($localKeyValue);
            $model->setAttribute($relation, $related);
        }

        return $collection;
    }

    /**
     * 预加载 HasMany 关联
     */
    protected function eagerLoadHasMany(Collection $collection, string $relation, $relationInstance, ?\Closure $constraints = null): Collection
    {
        $localKey = $relationInstance->getLocalKey();
        $foreignKey = $relationInstance->getForeignKey();
        $relatedClass = $relationInstance->getRelated();

        // 获取所有父模型的本地键值
        $localKeys = $collection->pluck($localKey)->unique()->filter()->all();

        if (empty($localKeys)) {
            return $collection;
        }

        // 批量查询关联模型
        $query = $relatedClass::query()->whereIn($foreignKey, $localKeys);

        // 应用自定义约束
        if ($constraints !== null) {
            $constraints($query);
        }

        $relatedModels = $query->get();

        // 按外键分组
        $relatedByKey = $relatedModels->groupBy($foreignKey);

        // 将关联模型附加到父模型
        foreach ($collection as $model) {
            $localKeyValue = $model->getAttribute($localKey);
            $related = $relatedByKey->get($localKeyValue, new Collection());
            $model->setAttribute($relation, $related);
        }

        return $collection;
    }

    /**
     * 预加载 BelongsTo 关联
     */
    protected function eagerLoadBelongsTo(Collection $collection, string $relation, $relationInstance, ?\Closure $constraints = null): Collection
    {
        $foreignKey = $relationInstance->getForeignKey();
        $localKey = $relationInstance->getLocalKey();
        $relatedClass = $relationInstance->getRelated();

        // 获取所有外键值
        $foreignKeys = $collection->pluck($foreignKey)->unique()->filter()->all();

        if (empty($foreignKeys)) {
            return $collection;
        }

        // 批量查询关联模型
        $query = $relatedClass::query()->whereIn($localKey, $foreignKeys);

        // 应用自定义约束
        if ($constraints !== null) {
            $constraints($query);
        }

        $relatedModels = $query->get();

        // 按主键分组
        $relatedByKey = $relatedModels->keyBy($localKey);

        // 将关联模型附加到父模型
        foreach ($collection as $model) {
            $foreignKeyValue = $model->getAttribute($foreignKey);
            $related = $relatedByKey->get($foreignKeyValue);
            $model->setAttribute($relation, $related);
        }

        return $collection;
    }

    /**
     * 预加载 BelongsToMany 关联
     *
     * 注意：BelongsToMany 的约束支持有限，因为使用了原生 SQL 查询
     * 如需复杂约束，建议在关联定义中处理
     */
    protected function eagerLoadBelongsToMany(Collection $collection, string $relation, $relationInstance, ?\Closure $constraints = null): Collection
    {
        $parentModel = $relationInstance->getParent();
        $relatedClass = $relationInstance->getRelated();
        $pivotTable = $relationInstance->getPivotTable();
        $foreignPivotKey = $relationInstance->getForeignPivotKey();
        $relatedPivotKey = $relationInstance->getRelatedPivotKey();

        // 获取所有父模型的主键值
        $parentPrimaryKey = $parentModel::getPrimaryKey();
        $parentKeys = $collection->pluck($parentPrimaryKey)->unique()->filter()->all();

        if (empty($parentKeys)) {
            return $collection;
        }

        // 查询中间表
        $pivotTableName = $this->connection->getTableName($pivotTable, $this->useGlobalTable);
        $relatedTableName = $this->connection->getTableName($relatedClass::getTable(), $this->useGlobalTable);
        $relatedPrimaryKey = $relatedClass::getPrimaryKey();

        $placeholders = implode(', ', array_fill(0, count($parentKeys), '%s'));

        // 基础 SQL（如果有约束，这里的实现会比较复杂，暂时不支持）
        $sql = sprintf(
            "SELECT %s.*, %s.%s as pivot_parent_key, %s.%s as pivot_related_key 
             FROM %s 
             INNER JOIN %s ON %s.%s = %s.%s 
             WHERE %s.%s IN (%s)",
            $relatedTableName,
            $pivotTableName,
            $foreignPivotKey,
            $pivotTableName,
            $relatedPivotKey,
            $relatedTableName,
            $pivotTableName,
            $relatedTableName,
            $relatedPrimaryKey,
            $pivotTableName,
            $relatedPivotKey,
            $pivotTableName,
            $foreignPivotKey,
            $placeholders
        );

        $results = $this->connection->select($sql, $parentKeys);

        // 将结果转换为模型
        $relatedModels = array_map(function ($row) use ($relatedClass) {
            return $relatedClass::newInstance($row);
        }, $results);

        // 如果有约束，对结果进行过滤（这是一个简化的实现）
        if ($constraints !== null && !empty($relatedModels)) {
            $tempCollection = new Collection($relatedModels);
            // 注意：这里的约束应用是在内存中进行的，不如在 SQL 中高效
            // 但对于 BelongsToMany，在 SQL 层面应用约束比较复杂
        }

        // 按父键分组
        $relatedByParentKey = [];
        foreach ($results as $index => $row) {
            $parentKey = $row['pivot_parent_key'];
            if (!isset($relatedByParentKey[$parentKey])) {
                $relatedByParentKey[$parentKey] = [];
            }
            $relatedByParentKey[$parentKey][] = $relatedModels[$index];
        }

        // 将关联模型附加到父模型
        foreach ($collection as $model) {
            $parentKeyValue = $model->getKey();
            $related = isset($relatedByParentKey[$parentKeyValue])
                ? new Collection($relatedByParentKey[$parentKeyValue])
                : new Collection();
            $model->setAttribute($relation, $related);
        }

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
