<?php

namespace Ezdeliver\RemoteConfig;

class RemoteRepoNotLinkedException extends \Exception
{
    public function __construct()
    {
        parent::__construct('No remote config repo is linked. Run "remote-config --link=<git-url>" first.');
    }
}
