<?php

namespace Ezdeliver\RemoteConfig;

use Ezdeliver\Config\Migrator;
use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\ProjectConfigNotFoundException;
use Ezdeliver\Config\StorageHandler;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RemoteConfigHandler
{
    private const int RETURN_CODE_OK = 0;
    private const int RETURN_CODE_ERROR = 1;
    private const int RETURN_CODE_NOOP = 2;

    /**
     * The remote storage handler is built lazily: its path only exists once a repo is linked.
     *
     * @param \Closure(): StorageHandler $remoteStorageHandlerFactory
     */
    public function __construct(
        private readonly RemoteConfigRepo $remoteConfigRepo,
        private readonly SymfonyStyle $io,
        private readonly Migrator $migrator,
        private readonly \Closure $remoteStorageHandlerFactory,
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
            $localChanges = $this->remoteConfigRepo->getLocalChanges();

            if ([] !== $localChanges && !$this->confirmDiscardingLocalChanges($localChanges)) {
                $this->io->warning('Aborted.');

                return self::RETURN_CODE_NOOP;
            }

            $this->remoteConfigRepo->sync();
        } catch (ProcessFailedException $e) {
            $this->io->error(sprintf('Failed to sync remote config repo: %s', $e->getMessage()));

            return self::RETURN_CODE_ERROR;
        }

        $this->io->success('Remote config repo synced.');

        return self::RETURN_CODE_OK;
    }

    public function upgrade(string $project): int
    {
        if (!$this->remoteConfigRepo->isLinked()) {
            $this->io->warning('No remote repo is linked, nothing to upgrade.');

            return self::RETURN_CODE_NOOP;
        }

        try {
            if ($this->remoteConfigRepo->isBehindUpstream()) {
                $this->io->error('The remote repo has new commits. Run "remote-config --sync" before upgrading.');

                return self::RETURN_CODE_ERROR;
            }

            if ($this->remoteConfigRepo->hasUncommittedChanges($project)) {
                $this->io->error(sprintf(
                    'Config "%s" has uncommitted changes in the local clone. Run "remote-config --sync" to discard them, then retry.',
                    $project
                ));

                return self::RETURN_CODE_ERROR;
            }

            $storageHandler = ($this->remoteStorageHandlerFactory)();

            if (!$storageHandler->isProjectConfigExists($project)) {
                throw new ProjectConfigNotFoundException($project);
            }

            $fromVersion = $storageHandler->peekConfigVersion($project);
            $result = $this->migrator->migrateProjectConfigInPlace($storageHandler, $project);

            if (self::RETURN_CODE_OK !== $result) {
                return $result;
            }

            $committed = $this->remoteConfigRepo->commitConfig($project, sprintf(
                'chore(config): upgrade %s from v%d to v%d',
                $project,
                $fromVersion,
                ProjectConfiguration::CURRENT_VERSION
            ));
        } catch (RemoteCloneNotFoundException|ProjectConfigNotFoundException $e) {
            $this->io->error($e->getMessage());

            return self::RETURN_CODE_ERROR;
        } catch (ProcessFailedException $e) {
            $this->io->error(sprintf('Git operation failed: %s', $e->getMessage()));

            return self::RETURN_CODE_ERROR;
        }

        if ($committed) {
            $this->io->success('Upgrade committed locally. Run "remote-config --push" to publish it.');
        }

        return self::RETURN_CODE_OK;
    }

    public function push(): int
    {
        if (!$this->remoteConfigRepo->isLinked()) {
            $this->io->warning('No remote repo is linked, nothing to push.');

            return self::RETURN_CODE_NOOP;
        }

        try {
            $unpushedCommits = $this->remoteConfigRepo->getUnpushedCommits();

            if ('' === $unpushedCommits) {
                $this->io->info('No local commit to push.');

                return self::RETURN_CODE_NOOP;
            }

            $this->io->section('Commits to push');
            $this->io->writeln($unpushedCommits);
            $this->io->section('Diff');
            $this->io->writeln($this->remoteConfigRepo->getUnpushedDiff());

            $uncommitted = $this->remoteConfigRepo->getLocalChanges()['uncommitted'] ?? null;

            if (null !== $uncommitted) {
                $this->io->warning(sprintf("These uncommitted changes will NOT be pushed:\n%s", $uncommitted));
            }

            $this->io->note(sprintf(
                'Teammates will need an ez-delivery version supporting config version %d to use upgraded configs.',
                ProjectConfiguration::CURRENT_VERSION
            ));

            if (!$this->io->confirm(sprintf('Push these commits to %s?', $this->remoteConfigRepo->getLink()->getGitUrl()), false)) {
                $this->io->warning('Aborted.');

                return self::RETURN_CODE_NOOP;
            }

            $this->remoteConfigRepo->push();
        } catch (RemoteCloneNotFoundException $e) {
            $this->io->error($e->getMessage());

            return self::RETURN_CODE_ERROR;
        } catch (ProcessFailedException $e) {
            $this->io->error(sprintf(
                'Push failed: %s. If the remote moved ahead, run "remote-config --sync" (discards your local commits) and redo the upgrade.',
                $e->getMessage()
            ));

            return self::RETURN_CODE_ERROR;
        }

        $this->io->success('Remote config repo pushed.');

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

    /**
     * @param array{uncommitted?: string, unpushed?: string} $localChanges
     */
    private function confirmDiscardingLocalChanges(array $localChanges): bool
    {
        $this->io->warning('The local clone has changes that are not on the remote. Syncing will discard them.');

        if (isset($localChanges['uncommitted'])) {
            $this->io->section('Uncommitted changes');
            $this->io->writeln($localChanges['uncommitted']);
        }

        if (isset($localChanges['unpushed'])) {
            $this->io->section('Unpushed commits');
            $this->io->writeln($localChanges['unpushed']);
        }

        return $this->io->confirm('Discard these changes and sync with the remote?', false);
    }
}
