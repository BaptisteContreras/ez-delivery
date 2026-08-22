<?php

namespace Ezdeliver\Factory;

use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Core\Vcs\IssueSelectorReleaseInfoFormatter;
use Ezdeliver\Core\Vcs\MrLabelReleaseInfoFormatter;
use Ezdeliver\Core\Vcs\PrReleaseInfoFormatter;

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
