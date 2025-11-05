<?php

/**
 * WP-ORM 读写分离使用示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WPOrm\Database\ConnectionManager;
use WPOrm\Model\Post;

// ============================================
// 1. 配置读写分离
// ============================================

ConnectionManager::configure([
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            // 读库配置（可以配置多个从库）
            'read' => [
                'host' => [
                    '192.168.1.2',  // 从库1
                    '192.168.1.3',  // 从库2
                ],
            ],
            // 写库配置
            'write' => [
                'host' => '192.168.1.1',  // 主库
            ],
            // 公共配置
            'driver' => 'mysql',
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => DB_CHARSET,
            'prefix' => $GLOBALS['wpdb']->prefix,
        ]
    ]
]);

// ============================================
// 2. 读操作（自动使用从库）
// ============================================

// 查询操作会自动路由到读库
$posts = Post::where('post_status', 'publish')
    ->orderBy('post_date', 'desc')
    ->limit(10)
    ->get();
// 连接: 192.168.1.2 或 192.168.1.3（随机选择）

$post = Post::find(1);
// 连接: 192.168.1.2 或 192.168.1.3

$count = Post::where('post_status', 'publish')->count();
// 连接: 192.168.1.2 或 192.168.1.3

// ============================================
// 3. 写操作（自动使用主库）
// ============================================

// 创建操作会自动路由到写库
$post = Post::create([
    'post_title' => 'New Post',
    'post_content' => 'Content',
    'post_status' => 'publish',
    'post_type' => 'post',
]);
// 连接: 192.168.1.1（主库）

// 更新操作
$post = Post::find(1);
$post->post_title = 'Updated Title';
$post->save();
// 连接: 192.168.1.1（主库）

// 删除操作
$post = Post::find(1);
$post->delete();
// 连接: 192.168.1.1（主库）

// ============================================
// 4. 事务（强制使用主库）
// ============================================

$connection = ConnectionManager::connection();

try {
    $connection->beginTransaction();
    
    // 事务中的所有操作都使用主库
    $post = Post::create([
        'post_title' => 'Transaction Post',
        'post_status' => 'publish',
    ]);
    
    $post->setMeta('key', 'value');
    
    $connection->commit();
} catch (\Exception $e) {
    $connection->rollback();
    throw $e;
}

// ============================================
// 5. 主从延迟处理
// ============================================

// 创建文章后立即读取（可能读到旧数据）
$post = Post::create([
    'post_title' => 'New Post',
    'post_status' => 'publish',
]);

// 方案1: 短暂延迟
usleep(100000);  // 延迟 100ms
$freshPost = Post::find($post->ID);

// 方案2: 强制从主库读取（需要自定义实现）
// $freshPost = Post::fromMaster()->find($post->ID);

// ============================================
// 6. 性能监控
// ============================================

function logQueryPerformance(): void
{
    $startTime = microtime(true);
    
    // 执行查询
    $posts = Post::where('post_status', 'publish')
        ->limit(100)
        ->get();
    
    $endTime = microtime(true);
    $duration = ($endTime - $startTime) * 1000;
    
    error_log("Query took {$duration}ms, returned {$posts->count()} posts");
}

// ============================================
// 7. 读写分离配置示例（wp-config.php）
// ============================================

/*
// 在 wp-config.php 中定义读写分离配置

// 主库（写）
define('DB_WRITE_HOST', '192.168.1.1');

// 从库（读）- 支持多个
define('DB_READ_HOST', '192.168.1.2,192.168.1.3');

// 或者使用数组
define('DB_READ_HOSTS', [
    '192.168.1.2',
    '192.168.1.3',
    '192.168.1.4',
]);
*/
