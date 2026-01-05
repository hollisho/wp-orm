<?php
namespace WPOrm\Builder;

/**
 * 原始 SQL 表达式
 * 用于在查询中插入原始 SQL 代码，不进行参数绑定
 */
class RawExpression
{
    protected string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * 获取原始 SQL 值
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * 转换为字符串
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
