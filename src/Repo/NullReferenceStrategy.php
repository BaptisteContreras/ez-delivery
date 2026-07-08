<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;

final class NullReferenceStrategy implements PrReferenceStrategy
{
    public function supportsReference(): bool
    {
        return false;
    }

    public function resolve(Pr $pr): ?PrReference
    {
        return null;
    }

    /**
     * @return array<string>
     */
    public function resolveLabels(Pr $pr): array
    {
        return $pr->getLabels();
    }
}
