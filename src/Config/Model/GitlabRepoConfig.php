<?php

namespace Ezdeliver\Config\Model;

class GitlabRepoConfig extends ProjectRepoConfig
{
    public const string TYPE = 'gitlab';

    public function __construct(
        private readonly string $namespace,
        string $name,
        string $apiToken,

    )
    {
        parent::__construct($name, $apiToken, self::TYPE);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }




}