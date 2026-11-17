<?php

namespace Ezdeliver\Tests\RemoteConfig;

use Ezdeliver\Config\Migrator;
use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\StorageHandler;
use Ezdeliver\RemoteConfig\RemoteCloneNotFoundException;
use Ezdeliver\RemoteConfig\RemoteConfigHandler;
use Ezdeliver\RemoteConfig\RemoteConfigRepo;
use Ezdeliver\RemoteConfig\RemotePathPrefixNotFoundException;
use Ezdeliver\RemoteConfig\RemoteRepoLink;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RemoteConfigHandlerTest extends TestCase
{
    private function makeHandler(
        RemoteConfigRepo $remoteConfigRepo,
        SymfonyStyle $io,
        ?Migrator $migrator = null,
        ?StorageHandler $remoteStorageHandler = null,
    ): RemoteConfigHandler {
        $remoteStorageHandler ??= $this->createMock(StorageHandler::class);

        return new RemoteConfigHandler(
            $remoteConfigRepo,
            $io,
            $migrator ?? $this->createMock(Migrator::class),
            fn () => $remoteStorageHandler,
        );
    }

    private function makeLinkedRepo(): RemoteConfigRepo
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(true);
        $remoteConfigRepo->method('getLink')->willReturn(new RemoteRepoLink('my-remote', 'git@gitlab.com:team/configs.git', 'configs'));

        return $remoteConfigRepo;
    }

    private function makeExistingRemoteStorage(int $version = 2): StorageHandler
    {
        $storageHandler = $this->createMock(StorageHandler::class);
        $storageHandler->method('isProjectConfigExists')->willReturn(true);
        $storageHandler->method('peekConfigVersion')->willReturn($version);

        return $storageHandler;
    }

    private function makeProcessFailedException(): ProcessFailedException
    {
        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(false);
        $process->method('isStarted')->willReturn(true);
        $process->method('getCommandLine')->willReturn('git clone');
        $process->method('getExitCode')->willReturn(1);
        $process->method('getExitCodeText')->willReturn('General error');
        $process->method('isOutputDisabled')->willReturn(true);
        $process->method('getWorkingDirectory')->willReturn('/tmp');

        return new ProcessFailedException($process);
    }

    public function testLinkWhenNotLinkedAsksForDetailsAndLinksWithoutConfirming(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(false);
        $remoteConfigRepo->expects($this->never())->method('unlink');
        $remoteConfigRepo->expects($this->once())->method('link')->with('my-remote', 'git@gitlab.com:team/configs.git', 'configs');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('confirm');
        $io->method('ask')->willReturnOnConsecutiveCalls('my-remote', 'git@gitlab.com:team/configs.git', 'configs');
        $io->expects($this->once())->method('success');

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io)->link());
    }

    public function testLinkWhenAlreadyLinkedAsksBeforeReplacing(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(true);
        $remoteConfigRepo->expects($this->once())->method('link')->with('new-remote', 'git@gitlab.com:team/new.git', '');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('confirm')->willReturn(true);
        $io->method('ask')->willReturnOnConsecutiveCalls('new-remote', 'git@gitlab.com:team/new.git', '');

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io)->link());
    }

    public function testLinkWhenAlreadyLinkedAndUserDeclinesDoesNotAskForDetailsOrLink(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(true);
        $remoteConfigRepo->expects($this->never())->method('link');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(false);
        $io->expects($this->never())->method('ask');
        $io->expects($this->once())->method('warning')->with('Aborted.');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $io)->link());
    }

    public function testLinkReturnsErrorWhenCloneFails(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(false);
        $remoteConfigRepo->method('link')->willThrowException($this->makeProcessFailedException());

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('ask')->willReturnOnConsecutiveCalls('my-remote', 'git@gitlab.com:team/configs.git', '');
        $io->expects($this->once())->method('error');
        $io->expects($this->never())->method('success');

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $io)->link());
    }

    public function testUnlinkWhenNotLinkedDoesNotAskAndIsANoOp(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(false);
        $remoteConfigRepo->expects($this->never())->method('unlink');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('confirm');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $io)->unlink());
    }

    public function testUnlinkAsksBeforeDeleting(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(true);
        $remoteConfigRepo->expects($this->once())->method('unlink');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('confirm')->willReturn(true);
        $io->expects($this->once())->method('success');

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io)->unlink());
    }

    public function testUnlinkWhenUserDeclinesDoesNothing(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(true);
        $remoteConfigRepo->expects($this->never())->method('unlink');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(false);
        $io->expects($this->once())->method('warning')->with('Aborted.');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $io)->unlink());
    }

    public function testSyncWhenNotLinkedIsANoOp(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(false);
        $remoteConfigRepo->expects($this->never())->method('sync');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $io)->sync());
    }

    public function testSyncDelegatesToRepoAndReportsSuccess(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(true);
        $remoteConfigRepo->expects($this->once())->method('sync');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('success');

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io)->sync());
    }

    public function testSyncReturnsErrorWhenGitOperationFails(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(true);
        $remoteConfigRepo->method('sync')->willThrowException($this->makeProcessFailedException());

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error');
        $io->expects($this->never())->method('success');

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $io)->sync());
    }

    public function testInfoWhenNotLinkedIsANoOp(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(false);
        $remoteConfigRepo->expects($this->never())->method('getLink');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $io)->info());
    }

    public function testInfoDisplaysLinkDetailsAndConfigList(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(true);
        $remoteConfigRepo->method('getLink')->willReturn(new RemoteRepoLink('my-remote', 'git@gitlab.com:team/configs.git', 'configs'));
        $remoteConfigRepo->method('list')->willReturn(['project-a', 'project-b']);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('definitionList');
        $io->expects($this->once())->method('section')->with('Available configs');
        $io->expects($this->exactly(2))->method('writeln');

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io)->info());
    }

    public function testInfoReturnsErrorWhenPathPrefixIsMissingFromTheClone(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(true);
        $remoteConfigRepo->method('getLink')->willReturn(new RemoteRepoLink('my-remote', 'git@gitlab.com:team/configs.git', 'configs'));
        $remoteConfigRepo->method('list')->willThrowException(new RemotePathPrefixNotFoundException('configs'));

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error');

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $io)->info());
    }

    public function testSyncWithLocalChangesAsksAndAbortsWhenDeclined(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('getLocalChanges')->willReturn(['uncommitted' => ' M configs/a.json', 'unpushed' => 'abc123 upgrade a']);
        $remoteConfigRepo->expects($this->never())->method('sync');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('confirm')->with($this->anything(), false)->willReturn(false);
        $io->expects($this->exactly(2))->method('section');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $io)->sync());
    }

    public function testSyncWithLocalChangesSyncsWhenConfirmed(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('getLocalChanges')->willReturn(['uncommitted' => ' M configs/a.json']);
        $remoteConfigRepo->expects($this->once())->method('sync');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(true);
        $io->expects($this->once())->method('success');

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io)->sync());
    }

    public function testSyncWithoutLocalChangesDoesNotAsk(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('getLocalChanges')->willReturn([]);
        $remoteConfigRepo->expects($this->once())->method('sync');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('confirm');

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io)->sync());
    }

    public function testUpgradeWhenNotLinkedIsANoOp(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(false);

        $migrator = $this->createMock(Migrator::class);
        $migrator->expects($this->never())->method('migrateProjectConfigInPlace');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $this->createMock(SymfonyStyle::class), $migrator)->upgrade('project-a'));
    }

    public function testUpgradeRefusesWhenCloneIsBehindTheRemote(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('isBehindUpstream')->willReturn(true);
        $remoteConfigRepo->expects($this->never())->method('commitConfig');

        $migrator = $this->createMock(Migrator::class);
        $migrator->expects($this->never())->method('migrateProjectConfigInPlace');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with($this->stringContains('--sync'));

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $io, $migrator)->upgrade('project-a'));
    }

    public function testUpgradeRefusesWhenTheConfigHasUncommittedChanges(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('isBehindUpstream')->willReturn(false);
        $remoteConfigRepo->method('hasUncommittedChanges')->with('project-a')->willReturn(true);

        $migrator = $this->createMock(Migrator::class);
        $migrator->expects($this->never())->method('migrateProjectConfigInPlace');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error');

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $io, $migrator)->upgrade('project-a'));
    }

    public function testUpgradeReturnsErrorWhenConfigDoesNotExist(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();

        $storageHandler = $this->createMock(StorageHandler::class);
        $storageHandler->method('isProjectConfigExists')->willReturn(false);

        $migrator = $this->createMock(Migrator::class);
        $migrator->expects($this->never())->method('migrateProjectConfigInPlace');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with($this->stringContains('project-a'));

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $io, $migrator, $storageHandler)->upgrade('project-a'));
    }

    public function testUpgradeReturnsErrorWhenCloneIsMissing(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('isBehindUpstream')->willThrowException(new RemoteCloneNotFoundException());

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error');

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $io)->upgrade('project-a'));
    }

    public function testUpgradeMigratesInPlaceAndCommitsOneCommitForTheConfig(): void
    {
        $storageHandler = $this->makeExistingRemoteStorage(2);

        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->expects($this->once())->method('commitConfig')->with(
            'project-a',
            sprintf('chore(config): upgrade project-a from v2 to v%d', ProjectConfiguration::CURRENT_VERSION)
        )->willReturn(true);

        $migrator = $this->createMock(Migrator::class);
        $migrator->expects($this->once())->method('migrateProjectConfigInPlace')->with($storageHandler, 'project-a')->willReturn(0);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('success')->with($this->stringContains('--push'));

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io, $migrator, $storageHandler)->upgrade('project-a'));
    }

    public function testUpgradeDoesNotCommitWhenMigrationFails(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->expects($this->never())->method('commitConfig');

        $migrator = $this->createMock(Migrator::class);
        $migrator->method('migrateProjectConfigInPlace')->willReturn(1);

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $this->createMock(SymfonyStyle::class), $migrator, $this->makeExistingRemoteStorage())->upgrade('project-a'));
    }

    public function testUpgradeOfAnAlreadyCurrentConfigReportsNoCommit(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('commitConfig')->willReturn(false);

        $migrator = $this->createMock(Migrator::class);
        $migrator->method('migrateProjectConfigInPlace')->willReturn(0);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('success');

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io, $migrator, $this->makeExistingRemoteStorage(ProjectConfiguration::CURRENT_VERSION))->upgrade('project-a'));
    }

    public function testUpgradeReturnsErrorWhenGitFails(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('isBehindUpstream')->willThrowException($this->makeProcessFailedException());

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error');

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $io)->upgrade('project-a'));
    }

    public function testPushWhenNotLinkedIsANoOp(): void
    {
        $remoteConfigRepo = $this->createMock(RemoteConfigRepo::class);
        $remoteConfigRepo->method('isLinked')->willReturn(false);
        $remoteConfigRepo->expects($this->never())->method('push');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $this->createMock(SymfonyStyle::class))->push());
    }

    public function testPushWithNothingToPushIsANoOpWithoutAsking(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('getUnpushedCommits')->willReturn('');
        $remoteConfigRepo->expects($this->never())->method('push');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('confirm');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $io)->push());
    }

    public function testPushShowsCommitsAndAbortsWhenDeclined(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('getUnpushedCommits')->willReturn('abc123 chore(config): upgrade project-a');
        $remoteConfigRepo->method('getUnpushedDiff')->willReturn('diff --git a/configs/project-a.json');
        $remoteConfigRepo->expects($this->never())->method('push');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('confirm')->with($this->stringContains('git@gitlab.com:team/configs.git'), false)->willReturn(false);
        $io->expects($this->once())->method('warning')->with('Aborted.');

        $this->assertSame(2, $this->makeHandler($remoteConfigRepo, $io)->push());
    }

    public function testPushPushesWhenConfirmed(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('getUnpushedCommits')->willReturn('abc123 chore(config): upgrade project-a');
        $remoteConfigRepo->expects($this->once())->method('push');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(true);
        $io->expects($this->once())->method('success');

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io)->push());
    }

    public function testPushWarnsAboutUncommittedChangesThatWillNotBePushed(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('getUnpushedCommits')->willReturn('abc123 upgrade');
        $remoteConfigRepo->method('getLocalChanges')->willReturn(['uncommitted' => ' M configs/b.json']);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning')->with($this->stringContains('configs/b.json'));
        $io->method('confirm')->willReturn(true);

        $this->assertSame(0, $this->makeHandler($remoteConfigRepo, $io)->push());
    }

    public function testPushReturnsErrorPointingToSyncWhenPushIsRejected(): void
    {
        $remoteConfigRepo = $this->makeLinkedRepo();
        $remoteConfigRepo->method('getUnpushedCommits')->willReturn('abc123 upgrade');
        $remoteConfigRepo->method('push')->willThrowException($this->makeProcessFailedException());

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(true);
        $io->expects($this->once())->method('error')->with($this->stringContains('--sync'));
        $io->expects($this->never())->method('success');

        $this->assertSame(1, $this->makeHandler($remoteConfigRepo, $io)->push());
    }
}
