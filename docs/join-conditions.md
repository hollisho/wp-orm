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
- 完全向后兼容旧的数组格式 JOIN
