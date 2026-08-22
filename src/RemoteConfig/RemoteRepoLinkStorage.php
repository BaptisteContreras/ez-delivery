<?php

namespace Ezdeliver\RemoteConfig;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

class RemoteRepoLinkStorage
{
    public function __construct(
        private readonly Filesystem $fs,
        private readonly SerializerInterface $serializer,
        private readonly string $linkFilePath,
    ) {
    }

    public function exists(): bool
    {
        return $this->fs->exists($this->linkFilePath);
    }

    public function save(RemoteRepoLink $link): void
    {
        $this->fs->dumpFile($this->linkFilePath, $this->serializer->serialize($link, 'json'));
    }

    public function load(): RemoteRepoLink
    {
        return $this->serializer->deserialize(
            file_get_contents($this->linkFilePath),
            RemoteRepoLink::class,
            'json'
        );
    }

    public function delete(): void
    {
        $this->fs->remove($this->linkFilePath);
    }
}
