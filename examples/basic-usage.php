<?php

/**
 * WP-ORM 基础使用示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WPOrm\Database\ConnectionManager;
use WPOrm\Model\Post;
use WPOrm\Model\User;
use WPOrm\Model\Term;

// ============================================
// 1. 配置数据库连接
// ============================================

ConnectionManager::configure([
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'prefix' => $GLOBALS['wpdb']->prefix,
        ]
    ]
]);

// ============================================
// 2. 基础查询
// ============================================

// 获取所有已发布的文章
$posts = Post::where('post_status', 'publish')
    ->where('post_type', 'post')
    ->orderBy('post_date', 'desc')
    ->limit(10)
    ->get();

// 根据 ID 查找
$post = Post::find(1);

// 获取文章数量
$count = Post::where('post_status', 'publish')->count();

// 检查是否存在
$exists = Post::where('post_name', 'hello-world')->exists();

// ============================================
// 3. 链式查询
// ============================================

$posts = Post::where('post_status', 'publish')
    ->where('post_type', 'post')
    ->whereIn('ID', [1, 2, 3])
    ->whereNotNull('post_excerpt')
    ->orderBy('post_date', 'desc')
    ->limit(20)
    ->offset(10)
    ->get();

// ============================================
// 4. 关联查询
// ============================================

// 获取文章及其作者
$post = Post::find(1);
$author = $post->author;  // 自动加载关联
echo $author->display_name;

// 预加载关联（避免 N+1 问题）
$posts = Post::where('post_status', 'publish')
    ->with('author', 'categories', 'tags')
    ->limit(10)
    ->get();

foreach ($posts as $post) {
    echo $post->post_title;
    echo $post->author->display_name;  // 已预加载，不会产生额外查询
}

// ============================================
// 5. 创建和更新
// ============================================

// 创建新文章
$post = Post::create([
    'post_title' => 'New Post',
    'post_content' => 'Content here',
    'post_status' => 'publish',
    'post_type' => 'post',
    'post_author' => 1,
]);

// 更新文章
$post = Post::find(1);
$post->post_title = 'Updated Title';
$post->save();

// 删除文章
$post = Post::find(1);
$post->delete();

// ============================================
// 6. 元数据操作
// ============================================

$post = Post::find(1);

// 获取元数据
$value = $post->getMeta('custom_field');

// 设置元数据
$post->setMeta('custom_field', 'value');

// ============================================
// 7. 分页
// ============================================

$result = Post::where('post_status', 'publish')
    ->orderBy('post_date', 'desc')
    ->paginate(15, 1);  // 每页15条，第1页

echo "Total: {$result['total']}";
echo "Current Page: {$result['current_page']}";
echo "Last Page: {$result['last_page']}";

foreach ($result['data'] as $post) {
    echo $post->post_title;
}

// ============================================
// 8. 多站点支持
// ============================================

// 查询指定站点的文章
$posts = Post::onSite(2)
    ->where('post_status', 'publish')
    ->get();

// 查询全局表（如用户表）
$users = User::global()->get();

// ============================================
// 9. 集合操作
// ============================================

$posts = Post::where('post_status', 'publish')->get();

// 映射
$titles = $posts->map(function ($post) {
    return $post->post_title;
});

// 过滤
$filtered = $posts->filter(function ($post) {
    return strlen($post->post_content) > 100;
});

// 提取列
$ids = $posts->pluck('ID');

// 排序
$sorted = $posts->sortBy('post_date', true);

// 分组
$grouped = $posts->groupBy('post_type');

// 转换为数组
$array = $posts->toArray();

// 转换为 JSON
$json = $posts->toJson();

// ============================================
// 10. 事务
// ============================================

$connection = ConnectionManager::connection();

try {
    $connection->beginTransaction();
    
    $post = Post::create([
        'post_title' => 'Transaction Test',
        'post_status' => 'publish',
    ]);
    
    $post->setMeta('key', 'value');
    
    $connection->commit();
} catch (\Exception $e) {
    $connection->rollback();
    throw $e;
}
