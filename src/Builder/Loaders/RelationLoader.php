<?php
namespace WPOrm\Builder\Loaders;

use WPOrm\Collection\Collection;

/**
 * 关联加载器接口
 * 定义关联预加载的标准接口
 */
interface RelationLoader
{
    /**
     * 加载关联数据
     *
     * @param Collection $collection 父模型集合
     * @param string $relation 关联名称
     * @param mixed $relationInstance 关联实例
     * @param \Closure|null $constraints 查询约束
     * @return Collection
     */
    public function load(Collection $collection, string $relation, $relationInstance, ?\Closure $constraints = null): Collection;
}
