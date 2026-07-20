<?php

namespace Ezdeliver\Config\Model;

class GitlabRepoConfig extends ProjectRepoConfig
{
    public const string TYPE = 'gitlab';

    public function __construct(
        private readonly string $namespace,
        string $name,
        string $apiTokenRef,
        private readonly PrSelectionMode $mode = PrSelectionMode::LinkedIssue,
    ) {
        parent::__construct($name, $apiTokenRef, self::TYPE);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getMode(): PrSelectionMode
    {
        return $this->mode;
    }
}
