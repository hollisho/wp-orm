<?php

/**
 * WP-ORM 多站点使用示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WPOrm\Database\ConnectionManager;
use WPOrm\Model\Post;
use WPOrm\Model\User;

// ============================================
// 1. 配置多站点数据库
// ============================================

ConnectionManager::configure([
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'prefix' => $GLOBALS['wpdb']->base_prefix,  // 使用基础前缀
        ]
    ]
]);

// ============================================
// 2. 查询当前站点的数据
// ============================================

// 自动使用当前站点的表前缀
$currentSiteId = get_current_blog_id();
ConnectionManager::setSiteId($currentSiteId);

$posts = Post::where('post_status', 'publish')->get();
// 查询: SELECT * FROM wp_2_posts WHERE post_status = 'publish'

// ============================================
// 3. 查询指定站点的数据
// ============================================

// 查询站点 2 的文章
$site2Posts = Post::onSite(2)
    ->where('post_status', 'publish')
    ->get();
// 查询: SELECT * FROM wp_2_posts WHERE post_status = 'publish'

// 查询站点 3 的文章
$site3Posts = Post::onSite(3)
    ->where('post_status', 'publish')
    ->get();
// 查询: SELECT * FROM wp_3_posts WHERE post_status = 'publish'

// ============================================
// 4. 查询全局表（用户表）
// ============================================

// 用户表是全局表，不受站点影响
$users = User::global()->get();
// 查询: SELECT * FROM wp_users

// 或者使用模型的全局表标记
$users = User::all();  // User 模型已标记为全局表
// 查询: SELECT * FROM wp_users

// ============================================
// 5. 跨站点查询
// ============================================

// 获取所有站点的文章数量
$sites = get_sites();
$totalPosts = 0;

foreach ($sites as $site) {
    $count = Post::onSite($site->blog_id)
        ->where('post_status', 'publish')
        ->count();
    
    $totalPosts += $count;
    
    echo "Site {$site->blog_id}: {$count} posts\n";
}

echo "Total posts across all sites: {$totalPosts}\n";

// ============================================
// 6. 在多站点间切换
// ============================================

// 切换到站点 2
switch_to_blog(2);
ConnectionManager::setSiteId(2);

$posts = Post::where('post_status', 'publish')->get();
// 查询站点 2 的文章

// 恢复到原站点
restore_current_blog();
ConnectionManager::setSiteId(get_current_blog_id());

// ============================================
// 7. 批量操作多个站点
// ============================================

function updateAllSitesPosts(string $oldStatus, string $newStatus): void
{
    $sites = get_sites();
    
    foreach ($sites as $site) {
        // 切换站点
        switch_to_blog($site->blog_id);
        ConnectionManager::setSiteId($site->blog_id);
        
        // 更新文章状态
        $posts = Post::where('post_status', $oldStatus)->get();
        
        foreach ($posts as $post) {
            $post->post_status = $newStatus;
            $post->save();
        }
        
        echo "Updated {$posts->count()} posts in site {$site->blog_id}\n";
    }
    
    // 恢复原站点
    restore_current_blog();
}

// 使用示例
updateAllSitesPosts('draft', 'publish');

// ============================================
// 8. 多站点数据聚合
// ============================================

function getNetworkStats(): array
{
    $sites = get_sites();
    $stats = [
        'total_sites' => count($sites),
        'total_posts' => 0,
        'total_users' => User::count(),  // 全局表
        'sites' => [],
    ];
    
    foreach ($sites as $site) {
        $postCount = Post::onSite($site->blog_id)
            ->where('post_status', 'publish')
            ->count();
        
        $stats['total_posts'] += $postCount;
        $stats['sites'][$site->blog_id] = [
            'domain' => $site->domain,
            'path' => $site->path,
            'posts' => $postCount,
        ];
    }
    
    return $stats;
}

$networkStats = getNetworkStats();
print_r($networkStats);
