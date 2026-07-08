<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Model\Pr;

interface LabelsUpdateStrategy
{
    /**
     * @param array<string> $labels
     */
    public function buildUpdate(Pr $pr, array $labels): LabelsUpdate;
}
