<?php

namespace WPOrm\Database;

/**
 * 数据库连接类
 * 支持读写分离
 */
class Connection
{
    protected array $config;
    protected ?\wpdb $readConnection = null;
    protected ?\wpdb $writeConnection = null;
    protected ?int $siteId;
    protected string $tablePrefix;

    public function __construct(array $config, ?int $siteId = null)
    {
        $this->config = $config;
        $this->siteId = $siteId;
        $this->tablePrefix = $this->resolveTablePrefix();
    }

    /**
     * 获取读连接
     */
    public function getReadConnection(): \wpdb
    {
        if ($this->readConnection === null) {
            $this->readConnection = $this->createConnection('read');
        }

        return $this->readConnection;
    }

    /**
     * 获取写连接
     */
    public function getWriteConnection(): \wpdb
    {
        if ($this->writeConnection === null) {
            $this->writeConnection = $this->createConnection('write');
        }

        return $this->writeConnection;
    }

    /**
     * 创建连接
     */
    protected function createConnection(string $type): \wpdb
    {
        global $wpdb;

        // 如果没有配置读写分离，使用全局 $wpdb
        if (!isset($this->config['read']) && !isset($this->config['write'])) {
            return $wpdb;
        }

        // 获取特定类型的配置
        $typeConfig = $this->config[$type] ?? [];
        $baseConfig = $this->config;

        // 合并配置
        $config = array_merge($baseConfig, $typeConfig);

        // 如果配置与全局 $wpdb 相同，直接返回
        if ($this->isSameAsGlobal($config)) {
            return $wpdb;
        }

        // 创建新的 wpdb 实例
        return new \wpdb(
            $config['username'] ?? DB_USER,
            $config['password'] ?? DB_PASSWORD,
            $config['database'] ?? DB_NAME,
            $config['host'] ?? DB_HOST
        );
    }

    /**
     * 检查配置是否与全局 $wpdb 相同
     */
    protected function isSameAsGlobal(array $config): bool
    {
        return ($config['host'] ?? DB_HOST) === DB_HOST
            && ($config['database'] ?? DB_NAME) === DB_NAME
            && ($config['username'] ?? DB_USER) === DB_USER;
    }

    /**
     * 执行查询（读操作）
     */
    public function select(string $query, array $bindings = []): array
    {
        $wpdb = $this->getReadConnection();
        $query = $this->prepareQuery($query, $bindings);

        $results = $wpdb->get_results($query, ARRAY_A);

        if ($wpdb->last_error) {
            throw new \RuntimeException("Database query error: {$wpdb->last_error}");
        }

        return $results ?: [];
    }

    /**
     * 执行查询（写操作）
     */
    public function statement(string $query, array $bindings = []): bool
    {
        $wpdb = $this->getWriteConnection();
        $query = $this->prepareQuery($query, $bindings);

        $result = $wpdb->query($query);

        if ($wpdb->last_error) {
            throw new \RuntimeException("Database query error: {$wpdb->last_error}");
        }

        return $result !== false;
    }

    /**
     * 插入数据
     */
    public function insert(string $query, array $bindings = []): int
    {
        $this->statement($query, $bindings);
        return $this->getWriteConnection()->insert_id;
    }

    /**
     * 更新数据
     */
    public function update(string $query, array $bindings = []): int
    {
        $this->statement($query, $bindings);
        return $this->getWriteConnection()->rows_affected;
    }

    /**
     * 删除数据
     */
    public function delete(string $query, array $bindings = []): int
    {
        $this->statement($query, $bindings);
        return $this->getWriteConnection()->rows_affected;
    }

    /**
     * 准备查询语句
     */
    protected function prepareQuery(string $query, array $bindings): string
    {
        if (empty($bindings)) {
            return $query;
        }

        $wpdb = $this->getReadConnection();
        return $wpdb->prepare($query, $bindings);
    }

    /**
     * 获取表前缀
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * 解析表前缀（支持多站点）
     */
    protected function resolveTablePrefix(): string
    {
        global $wpdb;

        $basePrefix = $this->config['prefix'] ?? $wpdb->prefix;

        // 如果指定了站点 ID，使用对应的前缀
        if ($this->siteId !== null && $this->siteId > 1) {
            return $basePrefix . $this->siteId . '_';
        }

        return $basePrefix;
    }

    /**
     * 获取完整表名
     */
    public function getTableName(string $table, bool $useGlobalTable = false): string
    {
        // 全局表不使用站点前缀
        if ($useGlobalTable) {
            $basePrefix = $this->config['prefix'] ?? $GLOBALS['wpdb']->base_prefix;
            return $basePrefix . $table;
        }

        return $this->tablePrefix . $table;
    }

    /**
     * 开始事务
     */
    public function beginTransaction(): void
    {
        $this->getWriteConnection()->query('START TRANSACTION');
    }

    /**
     * 提交事务
     */
    public function commit(): void
    {
        $this->getWriteConnection()->query('COMMIT');
    }

    /**
     * 回滚事务
     */
    public function rollback(): void
    {
        $this->getWriteConnection()->query('ROLLBACK');
    }

    /**
     * 获取最后插入的 ID
     */
    public function lastInsertId(): int
    {
        return $this->getWriteConnection()->insert_id;
    }
}
