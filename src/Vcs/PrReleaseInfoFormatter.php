<?php

namespace Ezdeliver\Vcs;

use Ezdeliver\Model\Pr;

/**
 * Formats the full release commit message that records what shipped.
 *
 * Only one implementation exists today (IssueSelectorReleaseInfoFormatter),
 * matching today's single selector kind (linked issue). As other selector
 * kinds are built, each may want its own release-record format - this
 * interface is the seam for that, resolved per PrSelectionMode by
 * PrReleaseInfoFormatterFactory.
 */
interface PrReleaseInfoFormatter
{
    /**
     * @param array<Pr> $prsDelivered
     */
    public function formatReleaseMessage(array $prsDelivered): string;
}
