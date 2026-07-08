<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;

/**
 * @rw#5 add some documentation
 */
interface PrReferenceStrategy
{
    public function supportsReference(): bool;

    public function resolve(Pr $pr): ?PrReference;

    /**
     * @return array<string>
     */
    public function resolveLabels(Pr $pr): array;
}
