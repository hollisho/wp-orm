<?php
namespace WPOrm\Builder\Loaders;

use WPOrm\Collection\Collection;
use WPOrm\Database\ConnectionManager;
use WPOrm\Relations\Relation;

/**
 * BelongsToMany 关联加载器
 */
class BelongsToManyLoader implements RelationLoader
{
    /**
     * 加载 BelongsToMany 关联
     */
    public function load(Collection $collection, string $relation, $relationInstance, ?\Closure $constraints = null): Collection
    {
        if ($collection->isEmpty()) {
            return $collection;
        }

        $parentPrimaryKey = $relationInstance->getParent()::getPrimaryKey();
        $parentKeys = $collection->pluck($parentPrimaryKey)->unique()->filter()->all();

        if (empty($parentKeys)) {
            return $collection;
        }

        $relatedClass = $relationInstance->getRelated();
        $pivotTable = $relationInstance->getPivotTable();
        $foreignPivotKey = $relationInstance->getForeignPivotKey();
        $relatedPivotKey = $relationInstance->getRelatedPivotKey();

        // 获取带前缀的完整表名
        $connection = ConnectionManager::connection();
        $relatedTable = $relatedClass::getTable();
        $relatedPrimaryKey = $relatedClass::getPrimaryKey();
        $relatedTableName = $connection->getTableName($relatedTable, false);
        $pivotTableName = $connection->getTableName($pivotTable, false);

        // 批量查询：使用原生 SQL 一次性获取所有数据
        $placeholders = implode(', ', array_fill(0, count($parentKeys), '%s'));

        // 构建基础 SQL
        $sql = sprintf(
            "SELECT %s.*, %s.%s as pivot_parent_key 
             FROM %s 
             INNER JOIN %s ON %s.%s = %s.%s 
             WHERE %s.%s IN (%s)",
            $relatedTableName,
            $pivotTableName,
            $foreignPivotKey,
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

        $bindings = $parentKeys;

        // 应用关联定义中的额外约束
        $firstModel = $collection->first();
        $tempRelation = $firstModel->$relation();

        if ($tempRelation instanceof Relation) {
            $tempQuery = $tempRelation->getQuery();
            $tempSql = $tempQuery->toSql();
            $tempBindings = $tempQuery->getBindings();

            // 提取 WHERE 子句（排除父键条件）
            if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER BY|\s+GROUP BY|\s+LIMIT|$)/i', $tempSql, $matches)) {
                $whereClause = $matches[1];

                // 移除父键的 WHERE 条件
                $whereClause = preg_replace(
                    '/' . preg_quote("{$pivotTableName}.{$foreignPivotKey}", '/') . '\s*=\s*%s\s*(?:AND\s+)?/i',
                    '',
                    $whereClause
                );

                $whereClause = trim($whereClause);
                if (!empty($whereClause) && $whereClause !== 'AND') {
                    $whereClause = preg_replace('/^AND\s+/i', '', $whereClause);

                    if (!empty($whereClause)) {
                        $sql .= " AND " . $whereClause;
                        $extraBindings = array_slice($tempBindings, 1);
                        $bindings = array_merge($bindings, $extraBindings);
                    }
                }
            }
        }

        // 执行查询
        $results = $connection->select($sql, $bindings);

        // 将结果转换为模型
        $relatedModels = array_map(function ($row) use ($relatedClass) {
            return $relatedClass::newInstance($row);
        }, $results);

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
}
