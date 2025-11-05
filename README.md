# WP-ORM

Eloquent-like ORM for WordPress with advanced features.

## Features

- 🔗 **Fluent Query Builder** - Chain methods for elegant queries
- 📖 **Read-Write Splitting** - Separate read and write database connections
- 🌐 **Multisite Support** - Automatic table prefix handling for WordPress multisite
- 🔄 **Relationships** - HasOne, HasMany, BelongsTo, ManyToMany
- 📦 **Collections** - Powerful collection methods
- 🎯 **Model Events** - Hooks for creating, updating, deleting
- 🔍 **Scopes** - Reusable query constraints

## Installation

```bash
composer require hollisho/wp-orm
```

## Quick Start

```php
use WPOrm\Model\Model;

class Post extends Model
{
    protected static string $table = 'posts';
    
    public function author()
    {
        return $this->belongsTo(User::class, 'post_author');
    }
}

// Query
$posts = Post::where('post_status', 'publish')
    ->orderBy('post_date', 'desc')
    ->limit(10)
    ->get();

// Find by ID
$post = Post::find(1);

// Relationships
$author = $post->author;
```

## Configuration

### Basic Setup

```php
use WPOrm\Database\ConnectionManager;

ConnectionManager::configure([
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
]);
```

### Read-Write Splitting

```php
ConnectionManager::configure([
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'read' => [
                'host' => '192.168.1.2',
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
]);
```

### Multisite Support

```php
// Automatically uses the correct table prefix for the current site
$posts = Post::onSite(2)->where('post_status', 'publish')->get();

// Or use global tables
$users = User::global()->get();
```

## License

MIT
