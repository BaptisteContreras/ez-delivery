<?php

namespace Ezdeliver\Config\Model;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(
    typeProperty: 'type',
    mapping: [
        GithubRepoConfig::TYPE => GithubRepoConfig::class,
        GitlabRepoConfig::TYPE => GitlabRepoConfig::class,
    ]
)]
abstract class ProjectRepoConfig
{
    public function __construct(
        private readonly string $name,
        private readonly string $apiTokenRef,
        private readonly string $type,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getApiTokenRef(): string
    {
        return $this->apiTokenRef;
    }

    public function getType(): string
    {
        return $this->type;
    }

    abstract public function getMode(): PrSelectionMode;
}
