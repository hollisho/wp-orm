<?php
namespace WPOrm\Builder\Loaders;

use WPOrm\Collection\Collection;

/**
 * HasMany 关联加载器
 */
class HasManyLoader implements RelationLoader
{
    /**
     * 加载 HasMany 关联
     */
    public function load(Collection $collection, string $relation, $relationInstance, ?\Closure $constraints = null): Collection
    {
        if ($collection->isEmpty()) {
            return $collection;
        }

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
}
