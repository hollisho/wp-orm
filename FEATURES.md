# WP-ORM 功能特性

## 核心功能

### 1. 流畅的查询构造器

```php
$posts = Post::where('post_status', 'publish')
    ->where('post_type', 'post')
    ->whereIn('ID', [1, 2, 3])
    ->whereNotNull('post_excerpt')
    ->orderBy('post_date', 'desc')
    ->limit(10)
    ->offset(5)
    ->get();
```

### 2. 读写分离

自动将读操作路由到从库，写操作路由到主库：

```php
// 配置
ConnectionManager::configure([
    'connections' => [
        'mysql' => [
            'read' => ['host' => ['192.168.1.2', '192.168.1.3']],
            'write' => ['host' => '192.168.1.1'],
            // ...
        ]
    ]
]);

// 读操作 -> 从库
$posts = Post::where('post_status', 'publish')->get();

// 写操作 -> 主库
$post = Post::create([...]);
```

### 3. 多站点支持

自动处理 WordPress 多站点的表前缀：

```php
// 查询当前站点
$posts = Post::where('post_status', 'publish')->get();

// 查询指定站点
$site2Posts = Post::onSite(2)->get();

// 查询全局表
$users = User::global()->get();
```

### 4. 模型关联

支持一对一、一对多、多对多关联：

```php
class Post extends Model
{
    public function author()
    {
        return $this->belongsTo(User::class, 'post_author');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'comment_post_ID');
    }

    public function categories()
    {
        return $this->belongsToMany(Term::class, 'term_relationships');
    }
}

// 使用关联
$post = Post::find(1);
$author = $post->author;
$comments = $post->comments;
```

### 5. 预加载（避免 N+1 问题）

```php
// 不好的做法 - N+1 问题
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->display_name;  // 每次都查询
}

// 好的做法 - 预加载
$posts = Post::with('author', 'categories')->get();
foreach ($posts as $post) {
    echo $post->author->display_name;  // 不产生额外查询
}
```

### 6. 集合操作

强大的集合方法：

```php
$posts = Post::where('post_status', 'publish')->get();

// 映射
$titles = $posts->map(fn($p) => $p->post_title);

// 过滤
$long = $posts->filter(fn($p) => strlen($p->post_content) > 1000);

// 提取列
$ids = $posts->pluck('ID');

// 排序
$sorted = $posts->sortBy('post_date', true);

// 分组
$grouped = $posts->groupBy('post_type');
```

### 7. 分页

```php
$result = Post::where('post_status', 'publish')
    ->paginate(15, 1);

// 返回:
[
    'data' => Collection,
    'total' => 100,
    'per_page' => 15,
    'current_page' => 1,
    'last_page' => 7,
]
```

### 8. 事务支持

```php
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

### 9. 元数据操作

```php
$post = Post::find(1);

// 获取元数据
$value = $post->getMeta('custom_field');

// 设置元数据
$post->setMeta('custom_field', 'value');

// 查询元数据
$posts = Post::whereHas('meta', function($query) {
    $query->where('meta_key', 'featured')
          ->where('meta_value', '1');
})->get();
```

### 10. 查询作用域

```php
class Post extends Model
{
    public function scopePublished($query)
    {
        return $query->where('post_status', 'publish');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('post_type', $type);
    }
}

// 使用作用域
$posts = Post::published()->ofType('post')->get();
```

## 预定义模型

### WordPress 核心模型

- `Post` - 文章/页面/自定义文章类型
- `User` - 用户
- `Comment` - 评论
- `Term` - 分类/标签
- `TermTaxonomy` - 分类法
- `PostMeta` - 文章元数据
- `UserMeta` - 用户元数据
- `CommentMeta` - 评论元数据
- `TermMeta` - 分类元数据

### 使用示例

```php
// 获取用户及其文章
$user = User::with('posts')->find(1);

// 获取分类及其文章
$category = Term::ofTaxonomy('category')
    ->with('posts')
    ->find(1);

// 获取文章及其所有关联
$post = Post::with('author', 'categories', 'tags', 'comments')
    ->find(1);
```

## 性能优化

### 1. 只查询需要的列

```php
Post::select('ID', 'post_title', 'post_date')
    ->where('post_status', 'publish')
    ->get();
```

### 2. 使用索引

```php
// 确保查询条件使用了索引
Post::where('post_status', 'publish')  // 有索引
    ->where('post_type', 'post')       // 有索引
    ->get();
```

### 3. 批量操作

```php
// 批量插入
foreach ($data as $item) {
    Post::create($item);
}

// 批量更新
Post::whereIn('ID', $ids)->update(['post_status' => 'publish']);
```

### 4. 缓存查询结果

```php
$cacheKey = 'popular_posts';
$posts = wp_cache_get($cacheKey);

if ($posts === false) {
    $posts = Post::where('post_status', 'publish')
        ->orderBy('comment_count', 'desc')
        ->limit(10)
        ->get();
    
    wp_cache_set($cacheKey, $posts, '', 3600);
}
```

## 与 WP-Foundation 集成

### 在控制器中使用

```php
class PostController
{
    public function index(Request $request): WP_REST_Response
    {
        $posts = Post::where('post_status', 'publish')
            ->with('author')
            ->paginate(15, $request->query('page', 1));

        return Response::success($posts);
    }
}
```

### 在服务类中使用

```php
class PostService
{
    public function getPopularPosts(int $limit = 10)
    {
        return Post::where('post_status', 'publish')
            ->orderBy('comment_count', 'desc')
            ->limit($limit)
            ->get();
    }
}
```

## 调试

### 查看生成的 SQL

```php
$query = Post::where('post_status', 'publish');
$sql = $query->toSql();
$bindings = $query->getBindings();

error_log("SQL: {$sql}");
error_log("Bindings: " . json_encode($bindings));
```

### 性能分析

```php
$start = microtime(true);

$posts = Post::where('post_status', 'publish')->get();

$duration = (microtime(true) - $start) * 1000;
error_log("Query took {$duration}ms");
```

## 最佳实践

1. **使用预加载避免 N+1 问题**
2. **只查询需要的列**
3. **使用分页处理大量数据**
4. **合理使用索引**
5. **缓存频繁查询的结果**
6. **使用事务保证数据一致性**
7. **读写分离提高性能**
8. **多站点环境注意表前缀**
