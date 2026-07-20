<?php

namespace Ezdeliver\Config\Model;

class GithubRepoConfig extends ProjectRepoConfig
{
    public const string TYPE = 'github';

    public function __construct(
        private readonly string $owner,
        string $name,
        string $apiTokenRef,
    ) {
        parent::__construct($name, $apiTokenRef, self::TYPE);
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getMode(): PrSelectionMode
    {
        return PrSelectionMode::LinkedIssue;
    }
}
