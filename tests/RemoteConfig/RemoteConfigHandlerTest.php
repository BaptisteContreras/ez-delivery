<?php

namespace Ezdeliver\Tests\RemoteConfig;

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
    private function makeHandler(RemoteConfigRepo $remoteConfigRepo, SymfonyStyle $io): RemoteConfigHandler
    {
        return new RemoteConfigHandler($remoteConfigRepo, $io);
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
}
