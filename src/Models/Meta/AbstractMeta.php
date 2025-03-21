<?php

namespace Dbout\WpOrm\Models\Meta;

use Dbout\WpOrm\Orm\AbstractModel;

/**
 * Class AbstractMeta
 * @package Dbout\WpOrm\Models\Meta
 */
abstract class AbstractMeta extends AbstractModel implements MetaInterface
{

    const META_KEY = 'meta_key';
    const META_VALUE = 'meta_value';

    /**
     * Disable created_at and updated_at
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $fillable = [
        self::META_VALUE, self::META_KEY
    ];

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->getAttribute(self::META_KEY);
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setKey($key)
    {
        $this->setAttribute(self::META_KEY, $key);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->getAttribute(self::META_VALUE);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->setAttribute(self::META_VALUE, $value);
        return $this;
    }

    /**
     * @return string
     */
    public function getKeyColumn()
    {
        return self::META_KEY;
    }

    /**
     * @return string
     */
    public function getValueColumn()
    {
        return self::META_VALUE;
    }
}
