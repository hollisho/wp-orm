<?php
namespace WPOrm\Builder\Loaders;

use WPOrm\Collection\Collection;

/**
 * BelongsTo 关联加载器
 */
class BelongsToLoader implements RelationLoader
{
    /**
     * 加载 BelongsTo 关联
     */
    public function load(Collection $collection, string $relation, $relationInstance, ?\Closure $constraints = null): Collection
    {
        if ($collection->isEmpty()) {
            return $collection;
        }

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
}
