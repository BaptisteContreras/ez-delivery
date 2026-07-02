<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;

interface PrReferenceStrategy
{
    public function supportsReference(): bool;

    public function resolve(Pr $pr): ?PrReference;
}
