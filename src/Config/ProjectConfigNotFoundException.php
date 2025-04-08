<?php

namespace Ezdeliver\Config;

class ProjectConfigNotFoundException extends \Exception
{
    public function __construct(string $project)
    {
        parent::__construct(sprintf('No config exists for project %s', $project));
    }
}
