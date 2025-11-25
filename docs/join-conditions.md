# JOIN 查询条件支持

## 概述

现在支持在 JOIN 子句中添加额外的 WHERE 条件，例如：

```sql
LEFT JOIN wp_postmeta AS pm_apply 
ON wp_posts.ID = pm_apply.post_id 
AND pm_apply.meta_key = '_college_tutorial_apply_status'
```

## 使用方法

### 1. 简单 JOIN（向后兼容）

```php
Post::query()
    ->leftJoin('postmeta', 'posts.ID', '=', 'postmeta.post_id')
    ->get();
```

### 2. JOIN 带额外条件

使用闭包来添加复杂条件：

```php
Post::query()
    ->leftJoin('postmeta AS pm_apply', function($join) {
        $join->on('posts.ID', '=', 'pm_apply.post_id')
             ->where('pm_apply.meta_key', '=', '_college_tutorial_apply_status');
    })
    ->get();
```

### 3. 多个条件

```php
Post::query()
    ->leftJoin('postmeta AS pm', function($join) {
        $join->on('posts.ID', '=', 'pm.post_id')
             ->where('pm.meta_key', '=', 'status')
             ->where('pm.meta_value', '=', 'active');
    })
    ->get();
```

### 4. OR 条件

```php
Post::query()
    ->leftJoin('postmeta AS pm', function($join) {
        $join->on('posts.ID', '=', 'pm.post_id')
             ->where('pm.meta_key', '=', 'status')
             ->orWhere('pm.meta_key', '=', 'priority');
    })
    ->get();
```

### 5. 多个 JOIN

```php
Post::query()
    ->leftJoin('postmeta AS pm1', function($join) {
        $join->on('posts.ID', '=', 'pm1.post_id')
             ->where('pm1.meta_key', '=', 'field1');
    })
    ->leftJoin('postmeta AS pm2', function($join) {
        $join->on('posts.ID', '=', 'pm2.post_id')
             ->where('pm2.meta_key', '=', 'field2');
    })
    ->get();
```

## JoinClause API

### on($first, $operator, $second, $boolean = 'and')
添加列与列的比较条件

### orOn($first, $operator, $second)
添加 OR 列比较条件

### where($column, $operator, $value, $boolean = 'and')
添加列与值的比较条件（用于 JOIN 中的固定值过滤）

### orWhere($column, $operator, $value)
添加 OR 值比较条件

## 实现细节

- 新增 `JoinClause` 类来管理复杂的 JOIN 条件
- `JoinCompiler` 支持编译 `JoinClause` 对象
- 自动收集和合并 JOIN 子句中的绑定参数
- 自动规范化列名（添加表前缀）
- 完全向后兼容旧的数组格式 JOIN

## 注意事项

- 列名会自动添加表前缀（如果需要）
- 绑定参数会自动收集并按正确顺序传递给数据库
- 支持表别名（AS）语法
- 第一个条件不会有 AND/OR 前缀，后续条件会自动添加

## 参数绑定说明

查询使用预处理语句（Prepared Statements）来防止 SQL 注入：

```php
// 你写的代码
$posts = Post::query()
    ->leftJoin('postmeta AS pm', function($join) {
        $join->on('posts.ID', '=', 'pm.post_id')
             ->where('pm.meta_key', '=', 'status');
    })
    ->get();

// 生成的 SQL（带占位符）
// LEFT JOIN wp_postmeta AS pm ON wp_posts.ID = pm.post_id AND pm.meta_key = ?

// 绑定参数
// ['status']

// 数据库实际执行的 SQL
// LEFT JOIN wp_postmeta AS pm ON wp_posts.ID = pm.post_id AND pm.meta_key = 'status'
```

这种方式的优点：
1. **安全**：防止 SQL 注入攻击
2. **高效**：数据库可以缓存查询计划
3. **自动**：框架自动处理参数转义和绑定

## 调试方法

```php
$query = Post::query()
    ->leftJoin('postmeta AS pm', function($join) {
        $join->on('posts.ID', '=', 'pm.post_id')
             ->where('pm.meta_key', '=', 'status');
    });

// 查看 SQL（带占位符）
echo $query->toSql();

// 查看绑定参数
print_r($query->getBindings());

// 查看最终 SQL（占位符被替换）
echo $query->toRawSql();
```
