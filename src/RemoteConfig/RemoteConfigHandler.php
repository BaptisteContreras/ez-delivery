<?php

namespace Ezdeliver\RemoteConfig;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RemoteConfigHandler
{
    private const int RETURN_CODE_OK = 0;
    private const int RETURN_CODE_ERROR = 1;
    private const int RETURN_CODE_NOOP = 2;

    public function __construct(
        private readonly RemoteConfigRepo $remoteConfigRepo,
        private readonly SymfonyStyle $io,
    ) {
    }

    public function link(): int
    {
        if ($this->remoteConfigRepo->isLinked()) {
            if (!$this->io->confirm('A remote repo is already linked. Replace it?', false)) {
                $this->io->warning('Aborted.');

                return self::RETURN_CODE_NOOP;
            }
        }

        $name = $this->io->ask('Name');
        $gitUrl = $this->io->ask('Git URL');
        $pathPrefix = $this->io->ask('Path prefix in the repo (empty for root)', '');

        $this->io->info(sprintf('Cloning %s...', $gitUrl));

        try {
            $this->remoteConfigRepo->link($name, $gitUrl, $pathPrefix);
        } catch (ProcessFailedException $e) {
            $this->io->error(sprintf('Failed to link remote config repo: %s', $e->getMessage()));

            return self::RETURN_CODE_ERROR;
        }

        $this->io->success('Remote config repo linked and cloned.');

        return self::RETURN_CODE_OK;
    }

    public function unlink(): int
    {
        if (!$this->remoteConfigRepo->isLinked()) {
            $this->io->info('No remote repo is linked, nothing to do.');

            return self::RETURN_CODE_NOOP;
        }

        if (!$this->io->confirm('Unlink the remote config repo? This deletes the local clone and cached state.', false)) {
            $this->io->warning('Aborted.');

            return self::RETURN_CODE_NOOP;
        }

        $this->remoteConfigRepo->unlink();
        $this->io->success('Remote repo unlinked.');

        return self::RETURN_CODE_OK;
    }

    public function sync(): int
    {
        if (!$this->remoteConfigRepo->isLinked()) {
            $this->io->warning('No remote repo is linked, nothing to sync.');

            return self::RETURN_CODE_NOOP;
        }

        try {
            $this->remoteConfigRepo->sync();
        } catch (ProcessFailedException $e) {
            $this->io->error(sprintf('Failed to sync remote config repo: %s', $e->getMessage()));

            return self::RETURN_CODE_ERROR;
        }

        $this->io->success('Remote config repo synced.');

        return self::RETURN_CODE_OK;
    }

    public function info(): int
    {
        if (!$this->remoteConfigRepo->isLinked()) {
            $this->io->warning('No remote repo is linked, nothing to show.');

            return self::RETURN_CODE_NOOP;
        }

        $link = $this->remoteConfigRepo->getLink();

        $this->io->definitionList(
            ['Name' => $link->getName()],
            ['Git URL' => $link->getGitUrl()],
            ['Path prefix' => '' !== $link->getPathPrefix() ? $link->getPathPrefix() : '(root)'],
            ['Last synced' => $link->getLastSyncedAt()->format('Y-m-d H:i:s')],
        );

        $this->io->section('Available configs');

        try {
            $projects = $this->remoteConfigRepo->list();
        } catch (RemotePathPrefixNotFoundException $e) {
            $this->io->error($e->getMessage());

            return self::RETURN_CODE_ERROR;
        }

        array_map(fn (string $project) => $this->io->writeln($project), $projects);

        return self::RETURN_CODE_OK;
    }
}
