<?php

namespace Ezdeliver\Tests\Config;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\StorageHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

class StorageHandlerTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir().'/ez-delivery-storage-handler-test-'.uniqid();
        $this->fs->mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    private function makeStorageHandler(): StorageHandler
    {
        return new StorageHandler(
            $this->createMock(SymfonyStyle::class),
            $this->fs,
            $this->createMock(SerializerInterface::class),
            $this->tmpDir,
        );
    }

    private function writeConfigFile(string $projectName, array $data): void
    {
        $this->fs->dumpFile(sprintf('%s/%s.json', $this->tmpDir, $projectName), json_encode($data));
    }

    public function testPeekConfigVersionReturnsVersionFromRawJson(): void
    {
        $this->writeConfigFile('myproject', ['version' => 2, 'projectName' => 'myproject']);

        $version = $this->makeStorageHandler()->peekConfigVersion('myproject');

        $this->assertSame(2, $version);
    }

    public function testPeekConfigVersionDefaultsToInitialVersionWhenKeyMissing(): void
    {
        $this->writeConfigFile('myproject', ['projectName' => 'myproject']);

        $version = $this->makeStorageHandler()->peekConfigVersion('myproject');

        $this->assertSame(ProjectConfiguration::INITIAL_VERSION, $version);
    }

    public function testLoadConfigAsArrayReturnsDecodedContent(): void
    {
        $this->writeConfigFile('myproject', ['projectName' => 'myproject', 'version' => 1]);

        $result = $this->makeStorageHandler()->loadConfigAsArray('myproject');

        $this->assertSame(['projectName' => 'myproject', 'version' => 1], $result);
    }

    public function testBackupConfigWritesBakFileWithOriginalContent(): void
    {
        $this->writeConfigFile('myproject', ['projectName' => 'myproject', 'version' => 1]);

        $this->makeStorageHandler()->backupConfig('myproject');

        $backupPath = sprintf('%s/myproject.json.bak', $this->tmpDir);
        $this->assertFileExists($backupPath);
        $this->assertSame(
            file_get_contents(sprintf('%s/myproject.json', $this->tmpDir)),
            file_get_contents($backupPath)
        );
    }

    public function testBackupConfigOverwritesExistingBackup(): void
    {
        $this->writeConfigFile('myproject', ['projectName' => 'myproject', 'version' => 1]);
        $this->makeStorageHandler()->backupConfig('myproject');

        $this->writeConfigFile('myproject', ['projectName' => 'myproject', 'version' => 2]);
        $this->makeStorageHandler()->backupConfig('myproject');

        $backupPath = sprintf('%s/myproject.json.bak', $this->tmpDir);
        $this->assertStringContainsString('"version":2', file_get_contents($backupPath));
    }
}
