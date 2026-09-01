<?php

namespace Ezdeliver\Config\Model;

class ProjectEnvConfig
{
    public function __construct(
        private readonly string $name,
        private readonly string $alreadyDeliveredLabel,
        private readonly string $toDeliverLabel,
        private readonly string $branchNamePattern,
        private readonly bool $deleteCurrentEnvReleaseBranch,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlreadyDeliveredLabel(): string
    {
        return $this->alreadyDeliveredLabel;
    }

    public function getToDeliverLabel(): string
    {
        return $this->toDeliverLabel;
    }

    public function getBranchNamePattern(): string
    {
        return $this->branchNamePattern;
    }

    public function isDeleteCurrentEnvReleaseBranch(): bool
    {
        return $this->deleteCurrentEnvReleaseBranch;
    }
}
