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
    public function display(array $prsToDeliver, bool $showMergeOrder): void
    {
        $headers = ['PR #ID', 'PR title', 'issue #ID', 'issue title', 'Number of commit'];

        $this->io->table(
            $showMergeOrder ? ['Order', ...$headers] : $headers,
            array_map(fn (Pr $pr) => [
                ...($showMergeOrder ? [$pr->getMergeOrder() ?? ''] : []),
                $pr->getId(),
                $pr->getTitle(),
                $pr->getSelectorId(),
                $pr->getSelectorTitle(),
                $pr->getCommitsCount(),
            ], $prsToDeliver)
        );
    }
}
