<?php

namespace Ezdeliver\Config\Model;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(
    typeProperty: 'type',
    mapping:  [
        GithubRepoConfig::TYPE => GithubRepoConfig::class,
    ]
)]
abstract class ProjectRepoConfig
{
    public function __construct(
        private readonly string $name,
        private readonly string $apiToken,
        private readonly string $type,
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function getType(): string
    {
        return $this->type;
    }



}