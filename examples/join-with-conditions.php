<?php
/**
 * JOIN 查询示例 - 支持复杂条件
 * 
 * 演示如何在 JOIN 子句中添加额外的 WHERE 条件
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WPOrm\Model\Post;

// 示例 1: 简单 JOIN（向后兼容）
$posts = Post::query()
    ->leftJoin('postmeta', 'posts.ID', '=', 'postmeta.post_id')
    ->get();

// 示例 2: JOIN 带额外条件（使用闭包）
$posts = Post::query()
    ->leftJoin('postmeta AS pm_apply', function($join) {
        $join->on('posts.ID', '=', 'pm_apply.post_id')
             ->where('pm_apply.meta_key', '=', '_college_tutorial_apply_status');
    })
    ->get();

// 示例 3: 多个 JOIN 条件
$posts = Post::query()
    ->leftJoin('postmeta AS pm1', function($join) {
        $join->on('posts.ID', '=', 'pm1.post_id')
             ->where('pm1.meta_key', '=', 'status')
             ->where('pm1.meta_value', '=', 'active');
    })
    ->leftJoin('postmeta AS pm2', function($join) {
        $join->on('posts.ID', '=', 'pm2.post_id')
             ->where('pm2.meta_key', '=', 'priority');
    })
    ->get();

// 示例 4: 使用 OR 条件
$posts = Post::query()
    ->leftJoin('postmeta AS pm', function($join) {
        $join->on('posts.ID', '=', 'pm.post_id')
             ->where('pm.meta_key', '=', 'status')
             ->orWhere('pm.meta_key', '=', 'priority');
    })
    ->get();

// 生成的 SQL 示例:
// SELECT * FROM wp_posts 
// LEFT JOIN wp_postmeta AS pm_apply ON wp_posts.ID = pm_apply.post_id AND pm_apply.meta_key = ?
