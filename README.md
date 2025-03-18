# Wordpress ORM with Eloquent

> 这是一个从 [dimitriBouteille/wp-orm](https://github.com/dimitriBouteille/wp-orm) fork 的项目。由于原仓库不支持7.0+，所以我fork了这个项目，并适配了7.0+的版本。

WordPress ORM wih Eloquent 是一个小型库,为 WordPress 添加了基本的 ORM 功能。它易于扩展,并包含了 WordPress 核心模型如文章(posts)、文章元数据(post metas)、用户(users)、评论(comments)等。
该 ORM 基于 [Eloquent ORM](https://laravel.com/docs/8.x/eloquent) 并使用 WordPress 的数据库连接(`wpdb` 类)。

此外,ORM 还提供了一个基于 [Phinx](https://phinx.org/) 的简单数据库迁移管理系统。

## 系统要求

基本要求与 [WordPress](https://wordpress.org/about/requirements/) 相同,另外还需要:

- PHP >= 7.0
- [Composer](https://getcomposer.org/) ❤️

> 为了简化此库的集成,我们建议将 WordPress 与以下工具之一配合使用: [Bedrock](https://roots.io/bedrock/), [Themosis](https://framework.themosis.com/) 或 [Wordplate](https://github.com/wordplate/wordplate#readme)。

## 安装

在 WordPress 项目根目录运行以下命令进行安装:

```bash
composer require hollisho/wp-orm