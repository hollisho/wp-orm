<?php

namespace Dbout\WpOrm\Models\Meta;

/**
 * Interface MetaInterface
 * @package Dbout\WpOrm\Models\Meta
 */
interface MetaInterface
{

    /**
     * @return string
     */
    public function getFkColumn();


    /**
     * @return string
     */
    public function getKeyColumn();

    /**
     * @return string
     */
    public function getValueColumn();
}