<?php

namespace WPOrm\Collection;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;

/**
 * 集合类
 * 提供强大的数组操作方法
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * 获取所有项
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * 获取第一项
     */
    public function first()
    {
        return $this->items[0] ?? null;
    }

    /**
     * 获取最后一项
     */
    public function last()
    {
        return end($this->items) ?: null;
    }

    /**
     * 映射
     */
    public function map(callable $callback): self
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * 过滤
     */
    public function filter(?callable $callback = null): self
    {
        if ($callback === null) {
            return new static(array_filter($this->items));
        }
        return new static(array_filter($this->items, $callback));
    }

    /**
     * 查找
     */
    public function find(callable $callback)
    {
        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * 提取列
     */
    public function pluck(string $key): self
    {
        $values = array_map(function ($item) use ($key) {
            if (is_object($item)) {
                return $item->$key ?? null;
            }
            return $item[$key] ?? null;
        }, $this->items);

        return new static($values);
    }

    /**
     * 去重
     */
    public function unique(): self
    {
        return new static(array_values(array_unique($this->items, SORT_REGULAR)));
    }

    /**
     * 按键索引
     */
    public function keyBy(string $key): self
    {
        $keyed = [];

        foreach ($this->items as $item) {
            $keyValue = is_object($item) ? ($item->$key ?? null) : ($item[$key] ?? null);
            if ($keyValue !== null) {
                $keyed[$keyValue] = $item;
            }
        }

        return new static($keyed);
    }

    /**
     * 获取指定键的值
     */
    public function get($key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * 排序
     */
    public function sortBy(string $key, bool $descending = false): self
    {
        $items = $this->items;

        usort($items, function ($a, $b) use ($key, $descending) {
            $aValue = is_object($a) ? $a->$key : $a[$key];
            $bValue = is_object($b) ? $b->$key : $b[$key];

            if ($aValue === $bValue) {
                return 0;
            }

            $result = $aValue < $bValue ? -1 : 1;

            return $descending ? -$result : $result;
        });

        return new static($items);
    }

    /**
     * 分组
     */
    public function groupBy(string $key): self
    {
        $groups = [];

        foreach ($this->items as $item) {
            $groupKey = is_object($item) ? ($item->$key ?? null) : ($item[$key] ?? null);
            if ($groupKey !== null) {
                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [];
                }
                $groups[$groupKey][] = $item;
            }
        }

        // 将每个分组转换为 Collection
        foreach ($groups as $key => $items) {
            $groups[$key] = new static($items);
        }

        return new static($groups);
    }

    /**
     * 分块
     */
    public function chunk(int $size): array
    {
        return array_chunk($this->items, $size);
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            return is_object($item) && method_exists($item, 'toArray')
                ? $item->toArray()
                : $item;
        }, $this->items);
    }

    /**
     * 转换为 JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * 是否为空
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * 是否不为空
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * 计数
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * 获取迭代器
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * ArrayAccess: offsetExists
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * ArrayAccess: offsetGet
     */
    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * ArrayAccess: offsetSet
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * ArrayAccess: offsetUnset
     */
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }
}
