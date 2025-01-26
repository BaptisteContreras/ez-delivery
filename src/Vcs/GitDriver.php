<?php

namespace Ezdeliver\Vcs;

use Castor\Context;
use function Castor\capture;

class GitDriver
{
    public function status(Context $context): string
    {
        return capture('git status', context: $context);
    }
}