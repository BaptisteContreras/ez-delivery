<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Model\Pr;

final class GitlabIssueLabelsUpdateStrategy implements LabelsUpdateStrategy
{
    /**
     * @param array<string> $labels
     */
    public function buildUpdate(Pr $pr, array $labels): LabelsUpdate
    {
        $reference = $pr->getReference();

        return new LabelsUpdate(
            $reference?->getId() ?? $pr->getId(), // @rw#2 in this strategy we explicitly wants to use the gitlab issue, so the PrReference. If the id or title does not exist this is a problem and we should not silently fallback to the PR's infos
            $reference?->getTitle() ?? $pr->getTitle(),
            $labels
        );
    }
}
