<?php

namespace Ezdeliver\Token;

class TokenNotFoundException extends \Exception
{
    public function __construct(string $ref)
    {
        parent::__construct(sprintf('No token found for reference "%s". Use "castor set-token %s" to set it.', $ref, $ref));
    }
}
