# WP-ORM 集成指南

## 在 wp-foundation 中使用

### 1. 安装依赖

```bash
composer require hollisho/wp-orm
```

### 2. 注册服务提供者

在 `bootstrap.php` 中注册 ORM 服务提供者：

```php
use WPFoundation\Database\OrmServiceProvider;

$app->register(new OrmServiceProvider($app->getContainer(), $app));
```

### 3. 配置数据库（可选）

创建 `config/database.php`：

```php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => DB_CHARSET,
            'prefix' => $GLOBALS['wpdb']->prefix,
        ]
    ]
];
```

### 4. 在控制器中使用

```php
namespace MyPlugin\Controllers;

use WPFoundation\Http\Request;
use WPFoundation\Http\Response;
use WPOrm\Model\Post;
use WP_REST_Response;

class PostController
{
    public function index(Request $request): WP_REST_Response
    {
        $posts = Post::where('post_status', 'publish')
            ->with('author', 'categories')
            ->orderBy('post_date', 'desc')
            ->paginate(15, $request->query('page', 1));

        return Response::success($posts);
    }

    public function show(Request $request): WP_REST_Response
    {
        $post = Post::find($request->route('id'));

        if (!$post) {
            return Response::notFound('Post not found');
        }

        return Response::success($post->toArray());
    }

    public function store(Request $request): WP_REST_Response
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

## 读写分离配置

### 在 wp-config.php 中配置

```php
// 主库（写）
define('DB_WRITE_HOST', '192.168.1.1');

// 从库（读）
define('DB_READ_HOST', '192.168.1.2');
```

### 或在 config/database.php 中配置

```php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'read' => [
                'host' => ['192.168.1.2', '192.168.1.3'],
            ],
            'write' => [
                'host' => '192.168.1.1',
            ],
            'driver' => 'mysql',
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => DB_CHARSET,
            'prefix' => $GLOBALS['wpdb']->prefix,
        ]
    ]
];
```

## 多站点支持

ORM 会自动检测多站点环境并使用正确的表前缀：

```php
// 查询当前站点的文章
$posts = Post::where('post_status', 'publish')->get();

// 查询指定站点的文章
$site2Posts = Post::onSite(2)->where('post_status', 'publish')->get();

// 查询全局表（用户）
$users = User::all();
```

## 自定义模型

```php
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
}
```

## 性能优化

### 1. 预加载关联（避免 N+1 问题）

```php
// 不好的做法
$posts = Post::where('post_status', 'publish')->get();
foreach ($posts as $post) {
    echo $post->author->display_name;  // 每次循环都会查询数据库
}

// 好的做法
$posts = Post::where('post_status', 'publish')
    ->with('author')  // 预加载作者
    ->get();
foreach ($posts as $post) {
    echo $post->author->display_name;  // 不会产生额外查询
}
```

### 2. 只查询需要的列

```php
$posts = Post::select('ID', 'post_title', 'post_date')
    ->where('post_status', 'publish')
    ->get();
```

### 3. 使用分页

```php
$result = Post::where('post_status', 'publish')
    ->paginate(20, $page);
```

## 事务处理

```php
use WPOrm\Database\ConnectionManager;

$connection = ConnectionManager::connection();

try {
    $connection->beginTransaction();
    
    $post = Post::create([...]);
    $post->setMeta('key', 'value');
    
    $connection->commit();
} catch (\Exception $e) {
    $connection->rollback();
    throw $e;
}
```

## 调试

```php
// 获取生成的 SQL
$query = Post::where('post_status', 'publish');
$sql = $query->toSql();
$bindings = $query->getBindings();

error_log("SQL: {$sql}");
error_log("Bindings: " . print_r($bindings, true));
```
