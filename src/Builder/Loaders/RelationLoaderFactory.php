<?php

namespace WPOrm\Builder\Loaders;

use WPOrm\Relations\HasOne;
use WPOrm\Relations\HasMany;
use WPOrm\Relations\BelongsTo;
use WPOrm\Relations\BelongsToMany;

/**
 * 关联加载器工厂
 * 根据关联类型创建对应的加载器
 */
class RelationLoaderFactory
{
    /**
     * 根据关联实例创建加载器
     */
    public function make($relationInstance): RelationLoader
    {
        $relationClass = get_class($relationInstance);

        switch ($relationClass) {
            case HasOne::class:
                return new HasOneLoader();
            case HasMany::class:
                return new HasManyLoader();
            case BelongsTo::class:
                return new BelongsToLoader();
            case BelongsToMany::class:
                return new BelongsToManyLoader();
            default:
                throw new \InvalidArgumentException("Unknown relation type: {$relationClass}");
        }
    }
}
