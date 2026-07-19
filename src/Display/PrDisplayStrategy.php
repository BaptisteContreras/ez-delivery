<?php

namespace Ezdeliver\Display;

use Ezdeliver\Model\Pr;

interface PrDisplayStrategy
{
    /**
     * @param array<Pr> $prsToDeliver
     */
    public function display(array $prsToDeliver): void;
}
