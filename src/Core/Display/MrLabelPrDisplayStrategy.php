<?php

namespace Ezdeliver\Core\Display;

use Ezdeliver\Core\Model\Pr;
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
    public function display(array $prsToDeliver, bool $showMergeOrder): void
    {
        $headers = ['PR #ID', 'PR title', 'Labels', 'Number of commit'];

        $this->io->table(
            $showMergeOrder ? ['Order', ...$headers] : $headers,
            array_map(fn (Pr $pr) => [
                ...($showMergeOrder ? [$pr->getMergeOrder() ?? ''] : []),
                $pr->getId(),
                $pr->getTitle(),
                implode(', ', $pr->getSelectorLabels()),
                $pr->getCommitsCount(),
            ], $prsToDeliver)
        );
    }
}
