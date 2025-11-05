<?php

/**
 * WP-ORM 数据库配置示例
 */

return [
    /*
    |--------------------------------------------------------------------------
    | 默认数据库连接
    |--------------------------------------------------------------------------
    */
    'default' => 'mysql',

    /*
    |--------------------------------------------------------------------------
    | 数据库连接配置
    |--------------------------------------------------------------------------
    */
    'connections' => [
        // 基础配置（使用 WordPress 全局 $wpdb）
        'mysql' => [
            'driver' => 'mysql',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => DB_CHARSET,
            'collation' => DB_COLLATE ?: 'utf8mb4_unicode_ci',
            'prefix' => $GLOBALS['wpdb']->prefix,
        ],

        // 读写分离配置示例
        'mysql_rw' => [
            'read' => [
                'host' => [
                    '192.168.1.2',  // 从库1
                    '192.168.1.3',  // 从库2
                ],
            ],
            'write' => [
                'host' => '192.168.1.1',  // 主库
            ],
            'driver' => 'mysql',
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => DB_CHARSET,
            'collation' => DB_COLLATE ?: 'utf8mb4_unicode_ci',
            'prefix' => $GLOBALS['wpdb']->prefix,
        ],

        // 多站点配置示例
        'multisite' => [
            'driver' => 'mysql',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => DB_CHARSET,
            'prefix' => $GLOBALS['wpdb']->base_prefix,  // 使用基础前缀
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 多站点配置
    |--------------------------------------------------------------------------
    */
    'multisite' => [
        'enabled' => is_multisite(),
        'current_site_id' => get_current_blog_id(),
    ],
];
