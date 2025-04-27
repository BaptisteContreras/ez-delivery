<?php

namespace Ezdeliver\Repo;

class GitlabLabelCorrespondanceNotFoundException extends \Exception
{
    public function __construct(string $labelTitle)
    {
        parent::__construct(sprintf('No label correspondance found for label %s', $labelTitle));
    }
}
