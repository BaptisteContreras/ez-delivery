<?php

namespace Ezdeliver\Core\Vcs;

use Castor\Context;
use Ezdeliver\Core\Model\Pr;
use Ezdeliver\Core\Vcs\Result\MergeResult;

interface MergeStrategyInterface
{
    public function mergePr(Context $context, Pr $pr): MergeResult;

    public function applyConflictResolution(Context $context): void;
}
