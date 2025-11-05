<?php

namespace WPOrm\Database;

/**
 * 数据库连接管理器
 * 支持读写分离和多站点
 */
class ConnectionManager
{
    protected static array $config = [];
    protected static array $connections = [];
    protected static ?string $defaultConnection = null;
    protected static ?int $currentSiteId = null;

    /**
     * 配置连接
     */
    public static function configure(array $config): void
    {
        static::$config = $config;
        static::$defaultConnection = $config['default'] ?? 'mysql';
    }

    /**
     * 获取连接
     */
    public static function connection(?string $name = null): Connection
    {
        $name = $name ?? static::$defaultConnection;
        $cacheKey = static::getConnectionCacheKey($name);

        if (!isset(static::$connections[$cacheKey])) {
            static::$connections[$cacheKey] = static::createConnection($name);
        }

        return static::$connections[$cacheKey];
    }

    /**
     * 创建连接
     */
    protected static function createConnection(string $name): Connection
    {
        $config = static::$config['connections'][$name] ?? [];

        if (empty($config)) {
            throw new \RuntimeException("Database connection [{$name}] not configured.");
        }

        return new Connection($config, static::$currentSiteId);
    }

    /**
     * 设置当前站点 ID（多站点模式）
     */
    public static function setSiteId(?int $siteId): void
    {
        static::$currentSiteId = $siteId;
        // 清除连接缓存，强制重新创建
        static::$connections = [];
    }

    /**
     * 获取当前站点 ID
     */
    public static function getSiteId(): ?int
    {
        return static::$currentSiteId;
    }

    /**
     * 获取连接缓存键
     */
    protected static function getConnectionCacheKey(string $name): string
    {
        $siteId = static::$currentSiteId ?? 'default';
        return "{$name}_{$siteId}";
    }

    /**
     * 重置所有连接
     */
    public static function reset(): void
    {
        static::$connections = [];
        static::$currentSiteId = null;
    }

    /**
     * 获取配置
     */
    public static function getConfig(?string $key = null)
    {
        if ($key === null) {
            return static::$config;
        }

        return static::$config[$key] ?? null;
    }
}
