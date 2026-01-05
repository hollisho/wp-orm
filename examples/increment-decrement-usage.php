<?php
/**
 * 自增/自减方法使用示例
 * 
 * 演示如何使用 increment() 和 decrement() 方法
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WPOrm\Model\Post;

// ============================================
// 示例 1: 基本自增
// ============================================

// 增加文章浏览次数
Post::query()
    ->where('ID', 123)
    ->increment('view_count');

// 等价于 SQL:
// UPDATE wp_posts SET view_count = `view_count` + 1 WHERE ID = 123

// ============================================
// 示例 2: 指定自增数量
// ============================================

// 增加点赞数 5 次
Post::query()
    ->where('ID', 123)
    ->increment('like_count', 5);

// 等价于 SQL:
// UPDATE wp_posts SET like_count = `like_count` + 5 WHERE ID = 123

// ============================================
// 示例 3: 自增时更新其他字段
// ============================================

// 增加浏览次数，同时更新最后浏览时间
Post::query()
    ->where('ID', 123)
    ->increment('view_count', 1, [
        'last_viewed_at' => date('Y-m-d H:i:s')
    ]);

// 等价于 SQL:
// UPDATE wp_posts 
// SET view_count = `view_count` + 1, last_viewed_at = '2026-01-05 10:30:00'
// WHERE ID = 123

// ============================================
// 示例 4: 基本自减
// ============================================

// 减少库存数量
Post::query()
    ->where('ID', 456)
    ->decrement('stock_count');

// 等价于 SQL:
// UPDATE wp_posts SET stock_count = `stock_count` - 1 WHERE ID = 456

// ============================================
// 示例 5: 指定自减数量
// ============================================

// 减少积分 10 分
Post::query()
    ->where('ID', 456)
    ->decrement('points', 10);

// 等价于 SQL:
// UPDATE wp_posts SET points = `points` - 10 WHERE ID = 456

// ============================================
// 示例 6: 自减时更新其他字段
// ============================================

// 减少库存，同时更新最后销售时间
Post::query()
    ->where('ID', 456)
    ->decrement('stock_count', 1, [
        'last_sold_at' => date('Y-m-d H:i:s'),
        'status' => 'selling'
    ]);

// 等价于 SQL:
// UPDATE wp_posts 
// SET stock_count = `stock_count` - 1, 
//     last_sold_at = '2026-01-05 10:30:00',
//     status = 'selling'
// WHERE ID = 456

// ============================================
// 示例 7: 批量更新
// ============================================

// 给所有已发布的文章增加浏览次数
$affectedRows = Post::query()
    ->where('post_status', 'publish')
    ->increment('view_count', 1);

echo "更新了 {$affectedRows} 篇文章\n";

// ============================================
// 示例 8: 使用 raw() 方法进行复杂更新
// ============================================

// 直接使用 raw 表达式
Post::query()
    ->where('ID', 789)
    ->update([
        'view_count' => Post::query()->raw('`view_count` + 1'),
        'like_count' => Post::query()->raw('`like_count` * 2'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

// 等价于 SQL:
// UPDATE wp_posts 
// SET view_count = `view_count` + 1,
//     like_count = `like_count` * 2,
//     updated_at = '2026-01-05 10:30:00'
// WHERE ID = 789

// ============================================
// 示例 9: 错误处理
// ============================================

try {
    // 传入非数字值会抛出异常
    Post::query()
        ->where('ID', 123)
        ->increment('view_count', 'invalid');
} catch (\InvalidArgumentException $e) {
    echo "错误: " . $e->getMessage() . "\n";
    // 输出: 错误: Non-numeric value passed to increment method.
}

// ============================================
// 示例 10: 实际应用场景
// ============================================

/**
 * 文章被访问时的处理
 */
function recordPostView(int $postId): void
{
    Post::query()
        ->where('ID', $postId)
        ->increment('view_count', 1, [
            'last_viewed_at' => date('Y-m-d H:i:s')
        ]);
}

/**
 * 用户点赞文章
 */
function likePost(int $postId): void
{
    Post::query()
        ->where('ID', $postId)
        ->increment('like_count');
}

/**
 * 用户取消点赞
 */
function unlikePost(int $postId): void
{
    Post::query()
        ->where('ID', $postId)
        ->decrement('like_count');
}

/**
 * 商品售出
 */
function sellProduct(int $productId, int $quantity): bool
{
    // 先检查库存
    $product = Post::query()->find($productId);
    
    if (!$product || $product->stock_count < $quantity) {
        return false;
    }
    
    // 减少库存
    Post::query()
        ->where('ID', $productId)
        ->decrement('stock_count', $quantity, [
            'last_sold_at' => date('Y-m-d H:i:s'),
            'total_sales' => Post::query()->raw('`total_sales` + ' . $quantity)
        ]);
    
    return true;
}
