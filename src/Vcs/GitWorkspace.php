<?php

namespace Ezdeliver\Vcs;

use Castor\Context;

class GitWorkspace
{

    public function __construct(
        private readonly GitDriver $gitDriver,
        private readonly Context $context,
    )
    {
    }

    public function isClear(): bool
    {
        return str_contains($this->gitDriver->status($this->context), 'nothing to commit, working tree clean');
    }
}