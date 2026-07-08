<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Model\Pr;

final class GitlabMrLabelsUpdateStrategy implements LabelsUpdateStrategy
{
    /**
     * @param array<string> $labels
     */
    public function buildUpdate(Pr $pr, array $labels): LabelsUpdate
    {
        return new LabelsUpdate($pr->getId(), $pr->getTitle(), $labels);
    }
}
