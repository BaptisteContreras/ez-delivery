<?php

namespace Ezdeliver\RemoteConfig;

class RemoteCloneNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('The remote config repo is linked but its local clone is missing. Run "remote-config --sync" first.');
    }
}
