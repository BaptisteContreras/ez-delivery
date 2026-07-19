<?php

namespace Ezdeliver\Display;

use Ezdeliver\Model\Pr;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MrLabelPrDisplayStrategy implements PrDisplayStrategy
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
            ['PR #ID', 'PR title', 'Labels', 'Number of commit'],
            array_map(fn (Pr $pr) => [
                $pr->getId(),
                $pr->getTitle(),
                implode(', ', $pr->getSelectorLabels()),
                $pr->getCommitsCount()],
                $prsToDeliver
            )
        );
    }
}
