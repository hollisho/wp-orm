# WP-ORM 快速开始

## 5 分钟上手指南

### 1. 安装

```bash
composer require hollisho/wp-orm
```

### 2. 配置（在 functions.php 或 bootstrap.php）

```php
use WPOrm\Database\ConnectionManager;

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
```

### 3. 开始使用

```php
use WPOrm\Model\Post;
use WPOrm\Model\User;

// 查询文章
$posts = Post::where('post_status', 'publish')
    ->orderBy('post_date', 'desc')
    ->limit(10)
    ->get();

// 根据 ID 查找
$post = Post::find(1);

// 创建文章
$post = Post::create([
    'post_title' => 'Hello World',
    'post_content' => 'This is my first post',
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
```

## 常用场景

### 场景 1: 获取最新文章

```php
$latestPosts = Post::where('post_status', 'publish')
    ->where('post_type', 'post')
    ->orderBy('post_date', 'desc')
    ->limit(5)
    ->get();

foreach ($latestPosts as $post) {
    echo $post->post_title . "\n";
}
```

### 场景 2: 获取文章及作者信息

```php
$posts = Post::where('post_status', 'publish')
    ->with('author')  // 预加载作者，避免 N+1 问题
    ->get();

foreach ($posts as $post) {
    echo "{$post->post_title} by {$post->author->display_name}\n";
}
```

### 场景 3: 分页显示

```php
$page = $_GET['page'] ?? 1;

$result = Post::where('post_status', 'publish')
    ->orderBy('post_date', 'desc')
    ->paginate(10, $page);

echo "Total: {$result['total']}\n";
echo "Page: {$result['current_page']} / {$result['last_page']}\n";

foreach ($result['data'] as $post) {
    echo $post->post_title . "\n";
}
```

### 场景 4: 搜索文章

```php
$keyword = $_GET['s'] ?? '';

$posts = Post::where('post_status', 'publish')
    ->where(function($query) use ($keyword) {
        $query->where('post_title', 'LIKE', "%{$keyword}%")
              ->orWhere('post_content', 'LIKE', "%{$keyword}%");
    })
    ->get();
```

### 场景 5: 获取用户的所有文章

```php
$user = User::find(1);
$userPosts = $user->posts()
    ->where('post_status', 'publish')
    ->orderBy('post_date', 'desc')
    ->get();
```

### 场景 6: 操作元数据

```php
$post = Post::find(1);

// 获取元数据
$featured = $post->getMeta('featured');

// 设置元数据
$post->setMeta('featured', '1');
$post->setMeta('views', '100');
```

### 场景 7: 批量操作

```php
// 批量发布草稿
$drafts = Post::where('post_status', 'draft')->get();

foreach ($drafts as $draft) {
    $draft->post_status = 'publish';
    $draft->save();
}

// 或使用事务
$connection = ConnectionManager::connection();

try {
    $connection->beginTransaction();
    
    foreach ($drafts as $draft) {
        $draft->post_status = 'publish';
        $draft->save();
    }
    
    $connection->commit();
} catch (\Exception $e) {
    $connection->rollback();
    throw $e;
}
```

## 读写分离配置

### 在 wp-config.php 中添加

```php
// 主库（写）
define('DB_WRITE_HOST', '192.168.1.1');

// 从库（读）
define('DB_READ_HOST', '192.168.1.2');
```

### 或在配置文件中

```php
ConnectionManager::configure([
    'connections' => [
        'mysql' => [
            'read' => [
                'host' => ['192.168.1.2', '192.168.1.3'],  // 多个从库
            ],
            'write' => [
                'host' => '192.168.1.1',
            ],
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'prefix' => $GLOBALS['wpdb']->prefix,
        ]
    ]
]);
```

## 多站点使用

```php
// 查询当前站点的文章
$posts = Post::where('post_status', 'publish')->get();

// 查询站点 2 的文章
$site2Posts = Post::onSite(2)
    ->where('post_status', 'publish')
    ->get();

// 查询所有站点的文章数量
$sites = get_sites();
foreach ($sites as $site) {
    $count = Post::onSite($site->blog_id)
        ->where('post_status', 'publish')
        ->count();
    
    echo "Site {$site->blog_id}: {$count} posts\n";
}
```

## 与 WP-Foundation 集成

### 1. 注册服务提供者

```php
// bootstrap.php
use WPFoundation\Database\OrmServiceProvider;

$app->register(new OrmServiceProvider($app->getContainer(), $app));
```

### 2. 在控制器中使用

```php
namespace MyPlugin\Controllers;

use WPFoundation\Http\Request;
use WPFoundation\Http\Response;
use WPOrm\Model\Post;

class PostController
{
    public function index(Request $request)
    {
        $posts = Post::where('post_status', 'publish')
            ->with('author')
            ->paginate(15, $request->query('page', 1));

        return Response::success($posts);
    }

    public function store(Request $request)
    {
        $post = Post::create([
            'post_title' => $request->input('title'),
            'post_content' => $request->input('content'),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => $request->user()->ID,
        ]);

        return Response::success($post->toArray(), 'Post created', 201);
    }
}
```

## 自定义模型

```php
namespace MyPlugin\Models;

use WPOrm\Model\Model;

class Product extends Model
{
    protected static string $table = 'products';
    protected static string $primaryKey = 'id';

    // 定义关联
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // 定义作用域
    public function scopeOnSale($query)
    {
        return $query->where('status', 'on_sale');
    }
}

// 使用
$products = Product::onSale()
    ->with('category')
    ->orderBy('created_at', 'desc')
    ->get();
```

## 调试技巧

```php
// 查看生成的 SQL
$query = Post::where('post_status', 'publish');
error_log($query->toSql());

// 查看绑定参数
error_log(print_r($query->getBindings(), true));

// 性能分析
$start = microtime(true);
$posts = Post::where('post_status', 'publish')->get();
$duration = (microtime(true) - $start) * 1000;
error_log("Query took {$duration}ms");
```

## 下一步

- 阅读 [完整文档](README.md)
- 查看 [功能特性](FEATURES.md)
- 学习 [集成指南](INTEGRATION.md)
- 浏览 [示例代码](examples/)
