<?php
namespace Dbout\WpOrm\Orm;

use Illuminate\Database\ConnectionResolverInterface;

/**
 * Class Resolver
 * @package Dbout\WpOrm\Orm
 */
class Resolver implements ConnectionResolverInterface
{

    /**
     * @param null $name
     * @return Database|\Illuminate\Database\ConnectionInterface|null
     */
    public function connection($name = null)
    {
        return Database::getInstance();
    }

    /**
     * @return string|void
     */
    public function getDefaultConnection()
    {
    }

    /**
     * @param string $name
     */
    public function setDefaultConnection($name)
    {
    }
}