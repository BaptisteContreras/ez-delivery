<?php

namespace Ezdeliver\Tests\RemoteConfig;

use Castor\Context;
use Ezdeliver\RemoteConfig\GitDriver;
use Ezdeliver\RemoteConfig\RemoteConfigRepo;
use Ezdeliver\RemoteConfig\RemotePathPrefixNotFoundException;
use Ezdeliver\RemoteConfig\RemoteRepoLink;
use Ezdeliver\RemoteConfig\RemoteRepoLinkStorage;
use Ezdeliver\RemoteConfig\RemoteRepoNotLinkedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class RemoteConfigRepoTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;
    private string $cloneBaseDirPath;
    private string $remoteStateBaseDirPath;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir().'/ez-delivery-remote-config-repo-test-'.uniqid();
        $this->fs->mkdir($this->tmpDir);
        $this->cloneBaseDirPath = $this->tmpDir.'/remote-repo-clone';
        $this->remoteStateBaseDirPath = $this->tmpDir.'/remote-state';
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    private function makeRepo(RemoteRepoLinkStorage $linkStorage, GitDriver $gitDriver): RemoteConfigRepo
    {
        return new RemoteConfigRepo(
            $linkStorage,
            $gitDriver,
            $this->fs,
            new Context(),
            $this->cloneBaseDirPath,
            $this->remoteStateBaseDirPath,
        );
    }

    private function cloneDirPathFor(string $name): string
    {
        return sprintf('%s/%s', $this->cloneBaseDirPath, $name);
    }

    private function remoteStateDirPathFor(string $name): string
    {
        return sprintf('%s/%s', $this->remoteStateBaseDirPath, $name);
    }

    private function makeLinkedStorage(string $pathPrefix = 'configs', string $name = 'my-remote'): RemoteRepoLinkStorage
    {
        $linkStorage = $this->createMock(RemoteRepoLinkStorage::class);
        $linkStorage->method('exists')->willReturn(true);
        $linkStorage->method('load')->willReturn(new RemoteRepoLink($name, 'git@gitlab.com:team/configs.git', $pathPrefix));

        return $linkStorage;
    }

    private function makeUnlinkedStorage(): RemoteRepoLinkStorage
    {
        $linkStorage = $this->createMock(RemoteRepoLinkStorage::class);
        $linkStorage->method('exists')->willReturn(false);

        return $linkStorage;
    }

    public function testIsLinkedReflectsStorageExistence(): void
    {
        $this->assertTrue($this->makeRepo($this->makeLinkedStorage(), $this->createMock(GitDriver::class))->isLinked());
        $this->assertFalse($this->makeRepo($this->makeUnlinkedStorage(), $this->createMock(GitDriver::class))->isLinked());
    }

    public function testLinkClonesIntoASubdirectoryNamedAfterTheLinkAndSaves(): void
    {
        $linkStorage = $this->makeUnlinkedStorage();
        $linkStorage->expects($this->once())->method('save')->with($this->callback(
            fn (RemoteRepoLink $link) => 'my-remote' === $link->getName()
                && 'git@gitlab.com:team/configs.git' === $link->getGitUrl()
                && 'configs' === $link->getPathPrefix()
        ));

        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->expects($this->once())->method('clone')->with(
            $this->isInstanceOf(Context::class),
            'git@gitlab.com:team/configs.git',
            $this->cloneDirPathFor('my-remote')
        );

        $this->makeRepo($linkStorage, $gitDriver)->link('my-remote', 'git@gitlab.com:team/configs.git', 'configs');
    }

    public function testLinkSavesLinkOnlyAfterCloneSucceeds(): void
    {
        // Regression guard: previously the link was persisted *before* cloning, so a failed
        // clone left a link record pointing at a repo that was never actually cloned.
        $linkStorage = $this->makeUnlinkedStorage();
        $linkStorage->expects($this->never())->method('save');

        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('clone')->willThrowException(new \RuntimeException('clone failed'));

        $this->expectException(\RuntimeException::class);

        $this->makeRepo($linkStorage, $gitDriver)->link('my-remote', 'git@gitlab.com:team/configs.git', 'configs');
    }

    public function testLinkWhenAlreadyLinkedReplacesTheExistingLink(): void
    {
        $linkStorage = $this->makeLinkedStorage();
        $linkStorage->expects($this->once())->method('delete');
        $linkStorage->expects($this->once())->method('save');

        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->expects($this->once())->method('clone');

        $this->makeRepo($linkStorage, $gitDriver)->link('new-remote', 'git@gitlab.com:team/new.git', '');
    }

    public function testLinkWhenReplacingRemovesThePreviousNamesCloneDirAndClonesIntoTheNewOne(): void
    {
        $this->fs->mkdir($this->cloneDirPathFor('old-remote'));
        $this->fs->mkdir($this->remoteStateDirPathFor('old-remote'));

        $linkStorage = $this->makeLinkedStorage(name: 'old-remote');

        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->expects($this->once())->method('clone')->with(
            $this->isInstanceOf(Context::class),
            'git@gitlab.com:team/new.git',
            $this->cloneDirPathFor('new-remote')
        );

        $this->makeRepo($linkStorage, $gitDriver)->link('new-remote', 'git@gitlab.com:team/new.git', '');

        $this->assertDirectoryDoesNotExist($this->cloneDirPathFor('old-remote'));
        $this->assertDirectoryDoesNotExist($this->remoteStateDirPathFor('old-remote'));
    }

    public function testUnlinkDeletesLinkCloneAndRemoteState(): void
    {
        $this->fs->mkdir($this->cloneDirPathFor('my-remote'));
        $this->fs->mkdir($this->remoteStateDirPathFor('my-remote'));

        $linkStorage = $this->makeLinkedStorage();
        $linkStorage->expects($this->once())->method('delete');

        $this->makeRepo($linkStorage, $this->createMock(GitDriver::class))->unlink();

        $this->assertDirectoryDoesNotExist($this->cloneDirPathFor('my-remote'));
        $this->assertDirectoryDoesNotExist($this->remoteStateDirPathFor('my-remote'));
    }

    public function testUnlinkWhenNotLinkedIsANoOp(): void
    {
        $linkStorage = $this->makeUnlinkedStorage();
        $linkStorage->expects($this->never())->method('delete');

        $this->makeRepo($linkStorage, $this->createMock(GitDriver::class))->unlink();
    }

    public function testSyncClonesWhenCloneDirIsMissing(): void
    {
        $linkStorage = $this->makeLinkedStorage();

        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->expects($this->once())->method('clone')->with(
            $this->isInstanceOf(Context::class),
            'git@gitlab.com:team/configs.git',
            $this->cloneDirPathFor('my-remote')
        );
        $gitDriver->expects($this->never())->method('fetchAndHardResetToUpstream');

        $this->makeRepo($linkStorage, $gitDriver)->sync();
    }

    public function testSyncFetchesAndResetsWhenCloneDirAlreadyExists(): void
    {
        $this->fs->mkdir($this->cloneDirPathFor('my-remote'));

        $linkStorage = $this->makeLinkedStorage();

        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->expects($this->never())->method('clone');
        $gitDriver->expects($this->once())->method('fetchAndHardResetToUpstream');

        $this->makeRepo($linkStorage, $gitDriver)->sync();
    }

    public function testSyncThrowsWhenNotLinked(): void
    {
        $this->expectException(RemoteRepoNotLinkedException::class);

        $this->makeRepo($this->makeUnlinkedStorage(), $this->createMock(GitDriver::class))->sync();
    }

    public function testSyncSavesLinkWithUpdatedLastSyncedAt(): void
    {
        $staleLink = new RemoteRepoLink('my-remote', 'git@gitlab.com:team/configs.git', 'configs', new \DateTimeImmutable('2020-01-01'));

        $linkStorage = $this->createMock(RemoteRepoLinkStorage::class);
        $linkStorage->method('exists')->willReturn(true);
        $linkStorage->method('load')->willReturn($staleLink);
        $linkStorage->expects($this->once())->method('save')->with($this->callback(
            fn (RemoteRepoLink $saved) => $saved->getLastSyncedAt() > $staleLink->getLastSyncedAt()
                && 'my-remote' === $saved->getName()
        ));

        $this->makeRepo($linkStorage, $this->createMock(GitDriver::class))->sync();
    }

    public function testListReturnsJsonBasenamesFromThePathPrefix(): void
    {
        $this->fs->mkdir($this->cloneDirPathFor('my-remote').'/configs');
        $this->fs->dumpFile($this->cloneDirPathFor('my-remote').'/configs/project-a.json', '{}');
        $this->fs->dumpFile($this->cloneDirPathFor('my-remote').'/configs/project-b.json', '{}');
        $this->fs->dumpFile($this->cloneDirPathFor('my-remote').'/configs/README.md', 'not a config');

        $result = $this->makeRepo($this->makeLinkedStorage(), $this->createMock(GitDriver::class))->list();

        sort($result);
        $this->assertSame(['project-a', 'project-b'], $result);
    }

    public function testListThrowsWhenNotLinked(): void
    {
        $this->expectException(RemoteRepoNotLinkedException::class);

        $this->makeRepo($this->makeUnlinkedStorage(), $this->createMock(GitDriver::class))->list();
    }

    public function testListThrowsWhenPathPrefixDoesNotExistInTheClone(): void
    {
        $this->fs->mkdir($this->cloneDirPathFor('my-remote'));

        $this->expectException(RemotePathPrefixNotFoundException::class);

        $this->makeRepo($this->makeLinkedStorage('configs'), $this->createMock(GitDriver::class))->list();
    }

    public function testGetConfigsPathReturnsCloneDirJoinedWithPathPrefix(): void
    {
        $path = $this->makeRepo($this->makeLinkedStorage('configs'), $this->createMock(GitDriver::class))->getConfigsPath();

        $this->assertSame($this->cloneDirPathFor('my-remote').'/configs', $path);
    }

    public function testGetConfigsPathReturnsCloneDirAloneWhenPrefixIsEmpty(): void
    {
        $path = $this->makeRepo($this->makeLinkedStorage(''), $this->createMock(GitDriver::class))->getConfigsPath();

        $this->assertSame($this->cloneDirPathFor('my-remote'), $path);
    }

    public function testGetConfigsPathThrowsWhenNotLinked(): void
    {
        $this->expectException(RemoteRepoNotLinkedException::class);

        $this->makeRepo($this->makeUnlinkedStorage(), $this->createMock(GitDriver::class))->getConfigsPath();
    }

    public function testGetLinkReturnsTheCurrentLink(): void
    {
        $link = $this->makeRepo($this->makeLinkedStorage(), $this->createMock(GitDriver::class))->getLink();

        $this->assertSame('my-remote', $link->getName());
    }

    public function testGetLinkThrowsWhenNotLinked(): void
    {
        $this->expectException(RemoteRepoNotLinkedException::class);

        $this->makeRepo($this->makeUnlinkedStorage(), $this->createMock(GitDriver::class))->getLink();
    }

    public function testGetRemoteStatePathReturnsBaseDirJoinedWithLinkName(): void
    {
        $path = $this->makeRepo($this->makeLinkedStorage(), $this->createMock(GitDriver::class))->getRemoteStatePath();

        $this->assertSame($this->remoteStateDirPathFor('my-remote'), $path);
    }

    public function testGetRemoteStatePathThrowsWhenNotLinked(): void
    {
        $this->expectException(RemoteRepoNotLinkedException::class);

        $this->makeRepo($this->makeUnlinkedStorage(), $this->createMock(GitDriver::class))->getRemoteStatePath();
    }
}
