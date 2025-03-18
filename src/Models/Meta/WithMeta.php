<?php

namespace Dbout\WpOrm\Models\Meta;

use Dbout\WpOrm\Exceptions\MetaNotSupportedException;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trait WithMeta
 * @package Dbout\WpOrm\Models\Meta
 */
trait WithMeta
{

    /**
     * @var AbstractMeta|null
     */
    protected $metaModel = null;

    /**
     * @var array
     */
    protected $_tmpMetas = [];

    /**
     * @return void
     */
    protected static function bootWithMeta()
    {
        static::saved(function($model) {
            $model->saveTmpMetas();
        });
    }

    /**
     * @throws MetaNotSupportedException
     * @throws \ReflectionException
     */
    public function initializeWithMeta()
    {
        $metaClass = $this->getMetaClass();
        $object = (new \ReflectionClass($metaClass));
        if (!$object->implementsInterface(MetaInterface::class)) {
            throw new MetaNotSupportedException(sprintf(
                "Model %s must be implement %s",
                $metaClass,
                MetaInterface::class
            ));
        }

        $this->metaModel = $object->newInstanceWithoutConstructor();
    }

    /**
     * @return HasMany
     */
    public function metas()
    {
        return $this->hasMany(get_class($this->metaModel), $this->metaModel->getFkColumn());
    }

    /**
     * @param string $metaKey
     * @return AbstractMeta|null
     */
    public function getMeta($metaKey)
    {
        return $this->metas()
            ->firstWhere($this->metaModel->getKeyColumn(), $metaKey);
    }

    /**
     * @param string $metaKey
     * @return mixed|null
     */
    public function getMetaValue($metaKey)
    {
        if (!$this->exists) {
            return $this->_tmpMetas[$metaKey] ?? null;
        }

        $meta = $this->getMeta($metaKey);
        if (!$meta) {
            return null;
        }

        return $meta->getValue();
    }

    /**
     * @param string $metaKey
     * @return bool
     */
    public function hasMeta($metaKey)
    {
        return $this->metas()
            ->where($this->metaModel->getKeyColumn(), $metaKey)
            ->exists();
    }

    /**
     * @param string $metaKey
     * @param $value
     * @return AbstractMeta|null
     */
    public function setMeta($metaKey, $value)
    {
        if (!$this->exists) {
            $this->_tmpMetas[$metaKey] = $value;
            return null;
        }

        $instance = $this->metas()
            ->firstOrNew([
                $this->metaModel->getKeyColumn() => $metaKey
            ]);

        $instance->fill([
            $this->metaModel->getValueColumn() => $value
        ])->save();

        return $instance;
    }

    /**
     * @param string $metaKey
     * @return bool
     */
    public function deleteMeta($metaKey)
    {
        if (!$this->exists) {
            unset($this->_tmpMetas[$metaKey]);
            return true;
        }

        return $this->metas()
            ->where($this->metaModel->getKeyColumn(), $metaKey)
            ->forceDelete();
    }

    /**
     * @return string
     */
    abstract public function getMetaClass();

    /**
     * @return void
     */
    protected function saveTmpMetas()
    {
        foreach ($this->_tmpMetas as $metaKey => $value) {
            $this->setMeta($metaKey, $value);
        }

        $this->_tmpMetas = [];
    }
}