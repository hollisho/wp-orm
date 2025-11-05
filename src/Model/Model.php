<?php

namespace WPOrm\Model;

use WPOrm\Builder\QueryBuilder;
use WPOrm\Database\ConnectionManager;

/**
 * 模型基类
 */
abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'ID';
    protected static bool $timestamps = false;
    protected static bool $isGlobalTable = false;

    protected array $attributes = [];
    protected array $original = [];
    protected array $relations = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * 创建新实例
     */
    public static function newInstance(array $attributes = []): static
    {
        $model = new static($attributes);
        $model->exists = true;
        $model->original = $attributes;
        return $model;
    }

    /**
     * 填充属性
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * 设置属性
     */
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * 获取属性
     */
    public function getAttribute(string $key)
    {
        // 检查关联
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // 检查属性
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        // 尝试加载关联
        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    /**
     * 获取关联值
     */
    protected function getRelationValue(string $key)
    {
        $relation = $this->$key();

        if ($relation instanceof \WPOrm\Relations\Relation) {
            $this->relations[$key] = $relation->getResults();
            return $this->relations[$key];
        }

        return null;
    }

    /**
     * 魔术方法：获取属性
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * 魔术方法：设置属性
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * 魔术方法：检查属性是否存在
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;

        // 包含关联
        foreach ($this->relations as $key => $value) {
            if (is_object($value) && method_exists($value, 'toArray')) {
                $attributes[$key] = $value->toArray();
            } elseif ($value instanceof \WPOrm\Collection\Collection) {
                $attributes[$key] = $value->toArray();
            } else {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /**
     * 转换为 JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * 保存模型
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * 执行插入
     */
    protected function performInsert(): bool
    {
        $connection = ConnectionManager::connection();
        $table = $connection->getTableName(static::$table, static::$isGlobalTable);

        $columns = array_keys($this->attributes);
        $placeholders = array_fill(0, count($columns), '%s');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $id = $connection->insert($sql, array_values($this->attributes));

        if ($id) {
            $this->setAttribute(static::$primaryKey, $id);
            $this->exists = true;
            $this->original = $this->attributes;
            return true;
        }

        return false;
    }

    /**
     * 执行更新
     */
    protected function performUpdate(): bool
    {
        $connection = ConnectionManager::connection();
        $table = $connection->getTableName(static::$table, static::$isGlobalTable);

        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        $sets = array_map(function ($key) {
            return "{$key} = %s";
        }, array_keys($dirty));

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = %s",
            $table,
            implode(', ', $sets),
            static::$primaryKey,
            '%s'
        );

        $bindings = array_merge(array_values($dirty), [$this->getKey()]);

        $affected = $connection->update($sql, $bindings);

        if ($affected > 0) {
            $this->original = $this->attributes;
            return true;
        }

        return false;
    }

    /**
     * 删除模型
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $connection = ConnectionManager::connection();
        $table = $connection->getTableName(static::$table, static::$isGlobalTable);

        $sql = sprintf(
            "DELETE FROM %s WHERE %s = %s",
            $table,
            static::$primaryKey,
            '%s'
        );

        $affected = $connection->delete($sql, [$this->getKey()]);

        if ($affected > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * 获取主键值
     */
    public function getKey()
    {
        return $this->getAttribute(static::$primaryKey);
    }

    /**
     * 获取已修改的属性
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * 检查是否已修改
     */
    public function isDirty(): bool
    {
        return !empty($this->getDirty());
    }

    /**
     * 创建查询构造器
     */
    public static function query(): QueryBuilder
    {
        $query = new QueryBuilder(static::class);

        if (static::$isGlobalTable) {
            $query->global();
        }

        return $query;
    }

    /**
     * 获取所有记录
     */
    public static function all(): \WPOrm\Collection\Collection
    {
        return static::query()->get();
    }

    /**
     * 根据 ID 查找
     */
    public static function find($id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * Where 查询
     */
    public static function where($column, $operator = null, $value = null): QueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    /**
     * 创建新记录
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * 获取表名
     */
    public static function getTable(): string
    {
        return static::$table;
    }

    /**
     * 获取主键名
     */
    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    /**
     * 指定站点（多站点模式）
     */
    public static function onSite(int $siteId): QueryBuilder
    {
        return static::query()->onSite($siteId);
    }

    /**
     * 使用全局表
     */
    public static function global(): QueryBuilder
    {
        return static::query()->global();
    }

    /**
     * 定义一对一关联
     */
    protected function hasOne(string $related, string $foreignKey, ?string $localKey = null)
    {
        $localKey = $localKey ?? static::$primaryKey;
        return new \WPOrm\Relations\HasOne($this, $related, $foreignKey, $localKey);
    }

    /**
     * 定义一对多关联
     */
    protected function hasMany(string $related, string $foreignKey, ?string $localKey = null)
    {
        $localKey = $localKey ?? static::$primaryKey;
        return new \WPOrm\Relations\HasMany($this, $related, $foreignKey, $localKey);
    }

    /**
     * 定义反向一对多关联
     */
    protected function belongsTo(string $related, string $foreignKey, ?string $ownerKey = null)
    {
        $ownerKey = $ownerKey ?? $related::getPrimaryKey();
        return new \WPOrm\Relations\BelongsTo($this, $related, $foreignKey, $ownerKey);
    }

    /**
     * 定义多对多关联
     */
    protected function belongsToMany(
        string $related,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey
    ) {
        return new \WPOrm\Relations\BelongsToMany(
            $this,
            $related,
            $pivotTable,
            $foreignPivotKey,
            $relatedPivotKey
        );
    }
}
