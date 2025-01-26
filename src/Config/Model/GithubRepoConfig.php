<?php

namespace Ezdeliver\Config\Model;

class GithubRepoConfig extends ProjectRepoConfig
{
    public const string TYPE = 'github';

    public function __construct(
        private readonly string $owner,
        string $name,
        string $apiToken,

    )
    {
        parent::__construct($name, $apiToken, self::TYPE);
    }

    public function getOwner(): string
    {
        return $this->owner;
    }



}