<?php

namespace Ezdeliver\RemoteConfig;

class RemotePathPrefixNotFoundException extends \Exception
{
    public function __construct(string $pathPrefix)
    {
        parent::__construct(sprintf('Path prefix "%s" does not exist in the linked remote repo.', $pathPrefix));
    }
}
