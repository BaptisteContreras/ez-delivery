<?php

namespace Ezdeliver\RemoteConfig;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class RemoteRepoLink
{
    public function __construct(
        private readonly string $name,
        private readonly string $gitUrl,
        private readonly string $pathPrefix = '',
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s'])]
        private readonly \DateTimeImmutable $lastSyncedAt = new \DateTimeImmutable(),
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGitUrl(): string
    {
        return $this->gitUrl;
    }

    public function getPathPrefix(): string
    {
        return $this->pathPrefix;
    }

    public function getLastSyncedAt(): \DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function withLastSyncedAt(\DateTimeImmutable $lastSyncedAt): self
    {
        return new self($this->name, $this->gitUrl, $this->pathPrefix, $lastSyncedAt);
    }
}
