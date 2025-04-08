<?php

namespace Ezdeliver\Repo;

class DriverNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Driver not found');
    }
}
