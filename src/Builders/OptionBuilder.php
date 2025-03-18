<?php

namespace Dbout\WpOrm\Builders;

use Dbout\WpOrm\Models\Option;

/**
 * Class OptionBuilder
 * @package Dbout\WpOrm\Builders
 */
class OptionBuilder extends AbstractBuilder
{

    /**
     * @param string $optionName
     * @return Option|null
     */
    public function findOption(string $optionName)
    {
        return $this->where(Option::NAME, $optionName)->first();
    }

    /**
     * @param string $optionName
     * @return $this
     */
    public function whereName(string $optionName): self
    {
        return $this->where(Option::NAME, $optionName);
    }
}
