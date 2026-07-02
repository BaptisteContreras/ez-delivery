<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;

final class IssueReferenceStrategy implements PrReferenceStrategy
{
    public function supportsReference(): bool
    {
        return true;
    }

    public function resolve(Pr $pr): ?PrReference
    {
        return $pr->getReference();
    }
}
