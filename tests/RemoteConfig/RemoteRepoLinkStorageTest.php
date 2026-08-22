<?php

namespace Ezdeliver\Tests\RemoteConfig;

use Ezdeliver\Factory\SfFactory;
use Ezdeliver\RemoteConfig\RemoteRepoLink;
use Ezdeliver\RemoteConfig\RemoteRepoLinkStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class RemoteRepoLinkStorageTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;
    private string $linkFilePath;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir().'/ez-delivery-remote-repo-link-storage-test-'.uniqid();
        $this->fs->mkdir($this->tmpDir);
        $this->linkFilePath = sprintf('%s/remote-repo.json', $this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    private function makeStorage(): RemoteRepoLinkStorage
    {
        return new RemoteRepoLinkStorage($this->fs, (new SfFactory())->createSfSerializer(), $this->linkFilePath);
    }

    public function testExistsReturnsFalseWhenNoLinkFileWritten(): void
    {
        $this->assertFalse($this->makeStorage()->exists());
    }

    public function testSaveThenExistsReturnsTrue(): void
    {
        $storage = $this->makeStorage();
        $storage->save(new RemoteRepoLink('my-remote', 'git@gitlab.com:team/configs.git', 'configs'));

        $this->assertTrue($storage->exists());
    }

    public function testSaveThenLoadRoundTripsNameGitUrlAndPathPrefix(): void
    {
        $storage = $this->makeStorage();
        $storage->save(new RemoteRepoLink('my-remote', 'git@gitlab.com:team/configs.git', 'configs'));

        $loaded = $storage->load();

        $this->assertSame('my-remote', $loaded->getName());
        $this->assertSame('git@gitlab.com:team/configs.git', $loaded->getGitUrl());
        $this->assertSame('configs', $loaded->getPathPrefix());
    }

    public function testSaveThenLoadRoundTripsLastSyncedAt(): void
    {
        $storage = $this->makeStorage();
        $lastSyncedAt = new \DateTimeImmutable('2024-03-05 10:00:00');
        $storage->save(new RemoteRepoLink('my-remote', 'git@gitlab.com:team/configs.git', 'configs', $lastSyncedAt));

        $loaded = $storage->load()->getLastSyncedAt();

        $this->assertSame($lastSyncedAt->format('Y-m-d H:i:s'), $loaded->format('Y-m-d H:i:s'));
    }

    public function testSaveWithDefaultPathPrefixRoundTripsToEmptyString(): void
    {
        $storage = $this->makeStorage();
        $storage->save(new RemoteRepoLink('my-remote', 'git@gitlab.com:team/configs.git'));

        $this->assertSame('', $storage->load()->getPathPrefix());
    }

    public function testSaveOverwritesExistingLink(): void
    {
        $storage = $this->makeStorage();
        $storage->save(new RemoteRepoLink('old-remote', 'git@gitlab.com:team/old.git', 'old'));
        $storage->save(new RemoteRepoLink('new-remote', 'git@gitlab.com:team/new.git', 'new'));

        $loaded = $storage->load();
        $this->assertSame('new-remote', $loaded->getName());
        $this->assertSame('git@gitlab.com:team/new.git', $loaded->getGitUrl());
        $this->assertSame('new', $loaded->getPathPrefix());
    }

    public function testDeleteRemovesTheLinkFile(): void
    {
        $storage = $this->makeStorage();
        $storage->save(new RemoteRepoLink('my-remote', 'git@gitlab.com:team/configs.git', 'configs'));

        $storage->delete();

        $this->assertFalse($storage->exists());
    }

    public function testDeleteOnNonExistentLinkDoesNotThrow(): void
    {
        $this->makeStorage()->delete();

        $this->assertFalse($this->makeStorage()->exists());
    }
}
