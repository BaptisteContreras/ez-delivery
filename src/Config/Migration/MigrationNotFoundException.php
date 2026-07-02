<?php

namespace Ezdeliver\Config\Migration;

class MigrationNotFoundException extends \Exception
{
    public function __construct(int $fromVersion)
    {
        parent::__construct(sprintf('No migration registered from config version %d', $fromVersion));
    }
}
