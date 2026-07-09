<?php

namespace Ezdeliver\Vcs;

use Ezdeliver\Model\Pr;

/**
 * Formats one Pr's line in the release commit message that records what shipped.
 *
 * Only one implementation exists today (IssueSelectorReleaseInfoFormatter),
 * matching today's single selector kind (linked issue). As other selector
 * kinds are built, each may want its own release-record format - this
 * interface is the seam for that, without yet deciding how the right
 * formatter gets chosen for a given Pr.
 */
interface PrReleaseInfoFormatter
{
    public function format(Pr $pr): string;
}
