<?php

namespace Ezdeliver\Factory;

use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Vcs\IssueSelectorReleaseInfoFormatter;
use Ezdeliver\Vcs\MrLabelReleaseInfoFormatter;
use Ezdeliver\Vcs\PrReleaseInfoFormatter;

class PrReleaseInfoFormatterFactory
{
    public function create(PrSelectionMode $mode): PrReleaseInfoFormatter
    {
        return match ($mode) {
            PrSelectionMode::LinkedIssue => new IssueSelectorReleaseInfoFormatter(),
            PrSelectionMode::MrLabel => new MrLabelReleaseInfoFormatter(),
        };
    }
}
