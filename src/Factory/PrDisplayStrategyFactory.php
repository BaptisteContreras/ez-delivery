<?php

namespace Ezdeliver\Factory;

use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Display\LinkedIssuePrDisplayStrategy;
use Ezdeliver\Display\MrLabelPrDisplayStrategy;
use Ezdeliver\Display\PrDisplayStrategy;
use Symfony\Component\Console\Style\SymfonyStyle;

class PrDisplayStrategyFactory
{
    public function __construct(
        private readonly SymfonyStyle $io,
    ) {
    }

    public function create(PrSelectionMode $mode): PrDisplayStrategy
    {
        return match ($mode) {
            PrSelectionMode::LinkedIssue => new LinkedIssuePrDisplayStrategy($this->io),
            PrSelectionMode::MrLabel => new MrLabelPrDisplayStrategy($this->io),
        };
    }
}
