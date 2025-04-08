<?php

namespace Ezdeliver\Vcs;

use Castor\Context;
use Ezdeliver\Model\Pr;
use Ezdeliver\Vcs\Result\MergeResult;

interface MergeStrategyInterface
{
    public function mergePr(Context $context, Pr $pr): MergeResult;

    public function applyConflictResolution(Context $context): void;
}
