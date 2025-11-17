<?php

namespace WPOrm\Builder\Support;

use WPOrm\Database\Connection;

/**
 * 表名规范化工具
 * 负责处理表名和字段名的前缀添加
 */
class TableNormalizer
{
    protected Connection $connection;
    protected bool $useGlobalTable;

    public function __construct(Connection $connection, bool $useGlobalTable = false)
    {
        $this->connection = $connection;
        $this->useGlobalTable = $useGlobalTable;
    }

    /**
     * 规范化表名（智能添加前缀）
     */
    public function normalizeTableName(string $table): string
    {
        // 如果以 @ 开头，表示完整表名，移除 @ 并返回
        if (strpos($table, '@') === 0) {
            return substr($table, 1);
        }

        // 如果已经包含前缀，直接返回
        global $wpdb;
        if (strpos($table, $wpdb->prefix) === 0) {
            return $table;
        }

        // 如果包含数据库名（如 db.table），不添加前缀
        if (strpos($table, '.') !== false && strpos($table, $wpdb->prefix) === false) {
            return $table;
        }

        // 否则添加 WordPress 表前缀
        return $this->connection->getTableName($table, $this->useGlobalTable);
    }

    /**
     * 规范化字段名（处理表名.字段名格式）
     */
    public function normalizeColumnName(string $column): string
    {
        // 如果不包含表名，直接返回
        if (strpos($column, '.') === false) {
            return $column;
        }

        // 分离表名和字段名
        $parts = explode('.', $column, 2);
        if (count($parts) !== 2) {
            return $column;
        }

        list($table, $field) = $parts;

        // 规范化表名
        $normalizedTable = $this->normalizeTableName($table);

        return "{$normalizedTable}.{$field}";
    }
}
