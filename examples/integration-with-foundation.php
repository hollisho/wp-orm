<?php

/**
 * WP-ORM 与 WP-Foundation 集成示例
 */

// ============================================
// 1. bootstrap.php - 注册 ORM 服务
// ============================================

use WPFoundation\Core\Application;
use WPFoundation\Database\OrmServiceProvider;

$app = new Application(__DIR__);

// 注册 ORM 服务提供者
$app->register(new OrmServiceProvider($app->getContainer(), $app));

// 其他服务提供者...
$app->boot();

// ============================================
// 2. 在控制器中使用 ORM
// ============================================

namespace MyPlugin\Controllers;

use WPFoundation\Http\Request;
use WPFoundation\Http\Response;
use WPOrm\Model\Post;
use WPOrm\Model\User;
use WP_REST_Response;

class PostController
{
    /**
     * 获取文章列表
     */
    public function index(Request $request): WP_REST_Response
    {
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 15);
        $status = $request->query('status', 'publish');

        // 使用 ORM 查询
        $result = Post::where('post_status', $status)
            ->where('post_type', 'post')
            ->with('author', 'categories')  // 预加载关联
            ->orderBy('post_date', 'desc')
            ->paginate($perPage, $page);

        return Response::success($result);
    }

    /**
     * 获取单个文章
     */
    public function show(Request $request): WP_REST_Response
    {
        $postId = $request->route('id');

        $post = Post::with('author', 'categories', 'tags')
            ->find($postId);

        if (!$post) {
            return Response::notFound('文章不存在');
        }

        return Response::success($post->toArray());
    }

    /**
     * 创建文章
     */
    public function store(Request $request): WP_REST_Response
    {
        // 验证
        $errors = $request->validate([
            'title' => 'required',
            'content' => 'required',
        ]);

        if (!empty($errors)) {
            return Response::validationError($errors);
        }

        // 使用 ORM 创建
        $post = Post::create([
            'post_title' => $request->input('title'),
            'post_content' => $request->input('content'),
            'post_status' => $request->input('status', 'draft'),
            'post_type' => 'post',
            'post_author' => $request->user()->ID,
        ]);

        // 设置元数据
        if ($request->has('meta')) {
            foreach ($request->input('meta') as $key => $value) {
                $post->setMeta($key, $value);
            }
        }

        return Response::success(
            $post->toArray(),
            '文章创建成功',
            201
        );
    }

    /**
     * 更新文章
     */
    public function update(Request $request): WP_REST_Response
    {
        $postId = $request->route('id');
        $post = Post::find($postId);

        if (!$post) {
            return Response::notFound('文章不存在');
        }

        // 更新属性
        if ($request->has('title')) {
            $post->post_title = $request->input('title');
        }

        if ($request->has('content')) {
            $post->post_content = $request->input('content');
        }

        if ($request->has('status')) {
            $post->post_status = $request->input('status');
        }

        $post->save();

        return Response::success($post->toArray(), '文章更新成功');
    }

    /**
     * 删除文章
     */
    public function destroy(Request $request): WP_REST_Response
    {
        $postId = $request->route('id');
        $post = Post::find($postId);

        if (!$post) {
            return Response::notFound('文章不存在');
        }

        $post->delete();

        return Response::success(null, '文章删除成功');
    }

    /**
     * 批量操作
     */
    public function bulkAction(Request $request): WP_REST_Response
    {
        $action = $request->input('action');
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return Response::badRequest('请选择要操作的文章');
        }

        $posts = Post::whereIn('ID', $ids)->get();

        switch ($action) {
            case 'publish':
                foreach ($posts as $post) {
                    $post->post_status = 'publish';
                    $post->save();
                }
                break;

            case 'draft':
                foreach ($posts as $post) {
                    $post->post_status = 'draft';
                    $post->save();
                }
                break;

            case 'delete':
                foreach ($posts as $post) {
                    $post->delete();
                }
                break;

            default:
                return Response::badRequest('无效的操作');
        }

        return Response::success(null, "成功处理 {$posts->count()} 篇文章");
    }
}

// ============================================
// 3. 在服务类中使用 ORM
// ============================================

namespace MyPlugin\Services;

use WPOrm\Model\Post;
use WPOrm\Model\User;
use WPOrm\Database\ConnectionManager;

class PostService
{
    /**
     * 获取热门文章
     */
    public function getPopularPosts(int $limit = 10): array
    {
        return Post::where('post_status', 'publish')
            ->where('post_type', 'post')
            ->orderBy('comment_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 获取用户的文章统计
     */
    public function getUserPostStats(int $userId): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [];
        }

        return [
            'total' => $user->posts()->count(),
            'published' => $user->posts()->where('post_status', 'publish')->count(),
            'draft' => $user->posts()->where('post_status', 'draft')->count(),
            'recent' => $user->posts()
                ->where('post_status', 'publish')
                ->orderBy('post_date', 'desc')
                ->limit(5)
                ->get()
                ->toArray(),
        ];
    }

    /**
     * 复制文章到其他站点（多站点）
     */
    public function copyPostToSite(int $postId, int $targetSiteId): ?int
    {
        $post = Post::find($postId);

        if (!$post) {
            return null;
        }

        // 切换到目标站点
        $connection = ConnectionManager::connection();
        $originalSiteId = ConnectionManager::getSiteId();
        ConnectionManager::setSiteId($targetSiteId);

        try {
            // 创建新文章
            $newPost = Post::create([
                'post_title' => $post->post_title,
                'post_content' => $post->post_content,
                'post_status' => $post->post_status,
                'post_type' => $post->post_type,
                'post_author' => $post->post_author,
            ]);

            // 恢复原站点
            ConnectionManager::setSiteId($originalSiteId);

            return $newPost->ID;
        } catch (\Exception $e) {
            ConnectionManager::setSiteId($originalSiteId);
            throw $e;
        }
    }
}

// ============================================
// 4. 自定义模型
// ============================================

namespace MyPlugin\Models;

use WPOrm\Model\Model;

class Product extends Model
{
    protected static string $table = 'products';
    protected static string $primaryKey = 'id';

    public function orders()
    {
        return $this->belongsToMany(
            Order::class,
            'order_items',
            'product_id',
            'order_id'
        );
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * 作用域：在售商品
     */
    public function scopeOnSale($query)
    {
        return $query->where('status', 'on_sale');
    }

    /**
     * 作用域：库存充足
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}

// 使用自定义模型
$products = Product::onSale()
    ->inStock()
    ->with('category')
    ->orderBy('created_at', 'desc')
    ->get();
