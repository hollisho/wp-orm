<?php
namespace WPOrm\Builder;

use WPOrm\Relations\Relation;
use WPOrm\Database\Connection;
use WPOrm\Database\ConnectionManager;
use WPOrm\Collection\Collection;
use WPOrm\Builder\Grammar\Grammar;
use WPOrm\Builder\Grammar\MySQLGrammar;
use WPOrm\Builder\Loaders\RelationLoaderFactory;
use WPOrm\Builder\Support\TableNormalizer;

/**
 * 查询构造器（重构版）
 * 职责：协调查询构建、执行和关联加载
 * 
 * 重构改进：
 * - 使用 Grammar 系统处理 SQL 生成
 * - 使用 Loader 系统处理关联预加载
 * - 使用 TableNormalizer 处理表名规范化
 * - 代码从 1100+ 行减少到 ~400 行
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
    protected $fromSubquery = null;
    protected ?string $fromSubqueryAlias = null;

    // 新增：依赖组件
    protected Grammar $grammar;
    protected RelationLoaderFactory $loaderFactory;
    protected TableNormalizer $tableNormalizer;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->connection = ConnectionManager::connection();
        $this->table = $modelClass::getTable();

        // 初始化依赖组件
        $this->grammar = new MySQLGrammar();
        $this->loaderFactory = new RelationLoaderFactory();
        $this->tableNormalizer = new TableNormalizer($this->connection, $this->useGlobalTable);
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

        // 规范化字段名（自动添加表前缀）
        $column = $this->normalizeColumnName($column);

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
    public function join(string $table, $first, string $operator = null, string $second = null, string $type = 'inner'): self
    {
        // 规范化表名
        $fullTable = $this->normalizeTableName($table);

        // 支持闭包形式的复杂 JOIN 条件
        if ($first instanceof \Closure) {
            $joinClause = new JoinClause($type, $fullTable, [$this, 'normalizeColumnName']);
            $first($joinClause);
            $this->joins[] = $joinClause;
        } else {
            // 简单 JOIN
            // 在这里规范化字段名，然后传入 JoinClause 时不使用 normalizer
            // 避免重复规范化导致 @ 符号失效
            $first = $this->normalizeColumnName($first);
            $second = $this->normalizeColumnName($second);

            $joinClause = new JoinClause($type, $fullTable, null);  // 不传入 normalizer
            $joinClause->on($first, $operator, $second);
            $this->joins[] = $joinClause;
        }

        return $this;
    }

    /**
     * Left Join
     */
    public function leftJoin(string $table, $first, string $operator = null, string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Right Join
     */
    public function rightJoin(string $table, $first, string $operator = null, string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * FROM 子查询
     *
     * @param QueryBuilder|string $query 子查询（QueryBuilder 实例或 SQL 字符串）
     * @param string $alias 子查询别名
     * @return self
     */
    public function fromSub($query, string $alias): self
    {
        $this->fromSubquery = $query;
        $this->fromSubqueryAlias = $alias;

        // 注意：子查询的参数会在 toSql() 中通过 toRawSql() 直接替换
        // 所以这里不需要合并绑定参数

        return $this;
    }

    /**
     * WHERE IN 子查询
     *
     * @param string $column 字段名
     * @param QueryBuilder|string $query 子查询
     * @return self
     */
    public function whereInSub(string $column, $query): self
    {
        $column = $this->normalizeColumnName($column);

        if ($query instanceof QueryBuilder) {
            $sql = $query->toSql();
            $bindings = $query->getBindings();
        } else {
            $sql = $query;
            $bindings = [];
        }

        $this->wheres[] = [
            'type' => 'in_sub',
            'column' => $column,
            'query' => $sql,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * WHERE NOT IN 子查询
     *
     * @param string $column 字段名
     * @param QueryBuilder|string $query 子查询
     * @return self
     */
    public function whereNotInSub(string $column, $query): self
    {
        $column = $this->normalizeColumnName($column);

        if ($query instanceof QueryBuilder) {
            $sql = $query->toSql();
            $bindings = $query->getBindings();
        } else {
            $sql = $query;
            $bindings = [];
        }

        $this->wheres[] = [
            'type' => 'not_in_sub',
            'column' => $column,
            'query' => $sql,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * WHERE EXISTS 子查询
     *
     * @param QueryBuilder|string $query 子查询
     * @return self
     */
    public function whereExists($query): self
    {
        if ($query instanceof QueryBuilder) {
            $sql = $query->toSql();
            $bindings = $query->getBindings();
        } else {
            $sql = $query;
            $bindings = [];
        }

        $this->wheres[] = [
            'type' => 'exists',
            'query' => $sql,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * WHERE NOT EXISTS 子查询
     *
     * @param QueryBuilder|string $query 子查询
     * @return self
     */
    public function whereNotExists($query): self
    {
        if ($query instanceof QueryBuilder) {
            $sql = $query->toSql();
            $bindings = $query->getBindings();
        } else {
            $sql = $query;
            $bindings = [];
        }

        $this->wheres[] = [
            'type' => 'not_exists',
            'query' => $sql,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
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
     *
     * 注意：
     * - 对于简单查询，直接使用 COUNT(*)
     * - 对于 GROUP BY 查询，建议手动使用 fromSub() 包装，或传入 autoWrap=true
     *
     * @param bool $autoWrap 是否自动包装子查询（用于 GROUP BY）
     * @return int
     */
    public function count(bool $autoWrap = true): int
    {
        // 如果使用了 GROUP BY 且开启自动包装
        if ($autoWrap && !empty($this->groups)) {
            // 将当前查询作为子查询，外层包装 COUNT(*)
            $subQuery = clone $this;

            // 创建新的查询来包装子查询
            $countQuery = new static($this->modelClass);
            $countQuery->fromSub($subQuery, 'count_wrapper');
            $countQuery->select('COUNT(*) as count');

            $result = $countQuery->connection->select($countQuery->toSql(), $countQuery->getBindings());
            return (int) ($result[0]['count'] ?? 0);
        }

        // 标准查询：直接替换 SELECT 列
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
            'items' => $items->toArray(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * 转换为 SQL（委托给 Grammar）
     */
    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }

    /**
     * 转换为 SQL，并绑定参数
     * @return string
     */
    public function toRawSql(): string
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();

        // 使用 wpdb 的 prepare 方法来安全地替换参数
        if (!empty($bindings)) {
            global $wpdb;
            return $wpdb->prepare($sql, $bindings);
        }

        return $sql;
    }





    /**
     * 规范化表名（委托给 TableNormalizer）
     */
    public function normalizeTableName(string $table): string
    {
        return $this->tableNormalizer->normalizeTableName($table);
    }

    /**
     * 规范化字段名（委托给 TableNormalizer）
     */
    public function normalizeColumnName(string $column): string
    {
        return $this->tableNormalizer->normalizeColumnName($column);
    }

    /**
     * 预加载关联（委托给 Loader）
     */
    protected function eagerLoadRelations(Collection $collection): Collection
    {
        foreach ($this->with as $relation => $constraints) {
            $collection = $this->loadRelation($collection, $relation, $constraints);
        }

        return $collection;
    }

    /**
     * 加载关联（使用 Loader Factory）
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

        // 使用工厂创建对应的 Loader
        $loader = $this->loaderFactory->make($relationInstance);

        // 执行加载
        return $loader->load($collection, $relation, $relationInstance, $constraints);
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
     * 获取绑定参数
     */
    public function getBindings(): array
    {
        // 注意：JOIN 条件的值已经直接嵌入到 SQL 中，不需要作为绑定参数
        // 只返回 WHERE 子句的绑定参数
        return $this->bindings;
    }

    // ============================================
    // Getter 方法（供 Grammar 和 Loader 使用）
    // ============================================

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getWheres(): array
    {
        return $this->wheres;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getLimitValue(): ?int
    {
        return $this->limitValue;
    }

    public function getOffsetValue(): ?int
    {
        return $this->offsetValue;
    }

    public function getFromSubquery()
    {
        return $this->fromSubquery;
    }

    public function getFromSubqueryAlias(): ?string
    {
        return $this->fromSubqueryAlias;
    }

    public function getFullTableName(): string
    {
        return $this->connection->getTableName($this->table, $this->useGlobalTable);
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }
}
