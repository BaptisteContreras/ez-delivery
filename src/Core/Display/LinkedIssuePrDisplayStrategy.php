<?php

namespace Ezdeliver\Core\Display;

use Ezdeliver\Core\Model\Pr;
use Symfony\Component\Console\Style\SymfonyStyle;

final class LinkedIssuePrDisplayStrategy implements PrDisplayStrategy
{
    public function __construct(
        private readonly SymfonyStyle $io,
    ) {
    }

    /**
     * @param array<Pr> $prsToDeliver
     */
    public function display(array $prsToDeliver): void
    {
        $this->io->table(
            ['PR #ID', 'PR title', 'issue #ID', 'issue title', 'Number of commit'],
            array_map(fn (Pr $pr) => [
                $pr->getId(),
                $pr->getTitle(),
                $pr->getSelectorId(),
                $pr->getSelectorTitle(),
                $pr->getCommitsCount()],
                $prsToDeliver
            )
        );
    }
}
