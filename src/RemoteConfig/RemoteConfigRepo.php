<?php

namespace Ezdeliver\RemoteConfig;

use Castor\Context;
use Symfony\Component\Filesystem\Filesystem;

class RemoteConfigRepo
{
    public function __construct(
        private readonly RemoteRepoLinkStorage $linkStorage,
        private readonly GitDriver $gitDriver,
        private readonly Filesystem $fs,
        private readonly Context $context,
        private readonly string $cloneBaseDirPath,
        private readonly string $remoteStateBaseDirPath,
    ) {
    }

    public function isLinked(): bool
    {
        return $this->linkStorage->exists();
    }

    public function link(string $name, string $gitUrl, string $pathPrefix): void
    {
        if ($this->isLinked()) {
            $this->unlink();
        }

        $this->gitDriver->clone($this->context, $gitUrl, $this->getCloneDirPath($name));

        $this->linkStorage->save(new RemoteRepoLink($name, $gitUrl, $pathPrefix));
    }

    public function unlink(): void
    {
        if (!$this->isLinked()) {
            return;
        }

        $link = $this->linkStorage->load();

        $this->linkStorage->delete();
        $this->fs->remove($this->getCloneDirPath($link->getName()));
        $this->fs->remove($this->getRemoteStateDirPath($link->getName()));
    }

    public function sync(): void
    {
        $link = $this->requireLink();
        $cloneDirPath = $this->getCloneDirPath($link->getName());

        if (!$this->fs->exists($cloneDirPath)) {
            $this->gitDriver->clone($this->context, $link->getGitUrl(), $cloneDirPath);
        } else {
            $this->gitDriver->fetchAndHardResetToUpstream(
                $this->context->withWorkingDirectory($cloneDirPath)
            );
        }

        $this->linkStorage->save($link->withLastSyncedAt(new \DateTimeImmutable()));
    }

    /**
     * @return string[]
     */
    public function list(): array
    {
        $configsPath = $this->getConfigsPath();

        if (!$this->fs->exists($configsPath)) {
            throw new RemotePathPrefixNotFoundException($this->requireLink()->getPathPrefix());
        }

        return array_map(
            fn (string $file) => basename($file, '.json'),
            glob($configsPath.'/*.json') ?: []
        );
    }

    /**
     * @throws RemoteRepoNotLinkedException
     */
    public function getLink(): RemoteRepoLink
    {
        return $this->requireLink();
    }

    /**
     * Resolves the on-disk directory remote project configs currently live in
     * (clone dir + configured path prefix). Used by `PackagerFactory` to point
     * a plain `Config\StorageHandler` at it.
     */
    public function getConfigsPath(): string
    {
        $link = $this->requireLink();

        return rtrim($this->getCloneDirPath($link->getName()).'/'.$link->getPathPrefix(), '/');
    }

    /**
     * Resolves the on-disk directory used to store package/delivery state
     * for the linked remote (paused deliveries, etc). Used by
     * `PackagerFactory` to point a plain `Core\StorageHandler` at it.
     *
     * @throws RemoteRepoNotLinkedException
     */
    public function getRemoteStatePath(): string
    {
        return $this->getRemoteStateDirPath($this->requireLink()->getName());
    }

    private function getCloneDirPath(string $name): string
    {
        return sprintf('%s/%s', $this->cloneBaseDirPath, $name);
    }

    private function getRemoteStateDirPath(string $name): string
    {
        return sprintf('%s/%s', $this->remoteStateBaseDirPath, $name);
    }

    private function requireLink(): RemoteRepoLink
    {
        if (!$this->isLinked()) {
            throw new RemoteRepoNotLinkedException();
        }

        return $this->linkStorage->load();
    }
}
