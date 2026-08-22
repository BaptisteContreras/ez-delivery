<?php

namespace Ezdeliver\Core\Display;

use Ezdeliver\Core\Model\Pr;

interface PrDisplayStrategy
{
    /**
     * @param array<Pr> $prsToDeliver
     */
    public function display(array $prsToDeliver): void;
}
