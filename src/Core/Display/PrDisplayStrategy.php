<?php

namespace Ezdeliver\Core\Display;

use Ezdeliver\Core\Model\Pr;

interface PrDisplayStrategy
{
    /**
     * @param array<Pr> $prsToDeliver
     * @param bool      $showMergeOrder adds the leading "Order" column (merge order sort enabled)
     */
    public function display(array $prsToDeliver, bool $showMergeOrder): void;
}
