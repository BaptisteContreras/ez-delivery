<?php

namespace Ezdeliver\Tests\Config;

use Ezdeliver\Config\Handler;
use Ezdeliver\Config\InteractiveBuilder;
use Ezdeliver\Config\Migration\ConfigMigration;
use Ezdeliver\Config\Migration\MigrationRunner;
use Ezdeliver\Config\Migrator;
use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\ProjectConfigNotFoundException;
use Ezdeliver\Config\StorageHandler;
use Ezdeliver\Factory\SfFactory;
use Ezdeliver\Token\TokenVault;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

// Note: TokenVault is `final`, so it cannot be created via $this->createMock() (PHPUnit
// refuses to double final classes). We construct a real instance pointed at an unused
// path instead, matching the convention used in tests/Repo/GitlabDriverSupportTest.php.
// TokenVault plays no role in the assertions below; it's only a required Handler dependency.

class HandlerTest extends TestCase
{
    private string $localDir;
    private string $legacyDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $base = sys_get_temp_dir().'/ez-delivery-handler-test-'.uniqid();
        $this->localDir = $base.'/local';
        $this->legacyDir = $base;
        $this->fs->mkdir($this->localDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->legacyDir);
    }

    private function writeConfigFile(string $dir, string $projectName, int $version): void
    {
        $this->fs->dumpFile(sprintf('%s/%s.json', $dir, $projectName), json_encode([
            'projectName' => $projectName,
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiTokenRef' => 'token-ref'],
            'envs' => [['name' => 'staging', 'alreadyDeliveredLabel' => 'delivered', 'toDeliverLabel' => 'to-deliver', 'branchNamePattern' => '%env%-%date_full%']],
            'version' => $version,
        ]));
    }

    private function makeHandler(bool $withLegacy = true, ?Migrator $migrator = null): Handler
    {
        $io = $this->createMock(SymfonyStyle::class);
        $serializer = (new SfFactory())->createSfSerializer();
        $storageHandler = new StorageHandler($io, $this->fs, $serializer, $this->localDir);
        $legacyStorageHandler = $withLegacy ? new StorageHandler($io, $this->fs, $serializer, $this->legacyDir) : null;

        return new Handler(
            $storageHandler,
            $this->createMock(InteractiveBuilder::class),
            new TokenVault($this->fs, sys_get_temp_dir().'/ez-delivery-handler-test-tokenvault-unused.json'),
            $legacyStorageHandler,
            $migrator,
        );
    }

    /**
     * @param array<ConfigMigration> $migrations
     */
    private function makeHandlerWithMigrator(array $migrations): Handler
    {
        $io = $this->createMock(SymfonyStyle::class);
        $serializer = (new SfFactory())->createSfSerializer();
        $migrator = new Migrator(
            new StorageHandler($io, $this->fs, $serializer, $this->localDir),
            new StorageHandler($io, $this->fs, $serializer, $this->legacyDir),
            new MigrationRunner($migrations),
            $serializer,
            $io,
        );

        return $this->makeHandler(migrator: $migrator);
    }

    private function makeMigration(int $from, int $to, callable $transform): ConfigMigration
    {
        $migration = $this->createMock(ConfigMigration::class);
        $migration->method('getFromVersion')->willReturn($from);
        $migration->method('getToVersion')->willReturn($to);
        $migration->method('migrate')->willReturnCallback($transform);

        return $migration;
    }

    public function testLoadProjectConfigReadsFromLocalDirWhenPresent(): void
    {
        $this->writeConfigFile($this->localDir, 'myproject', 3);

        $config = $this->makeHandler()->loadProjectConfig('myproject');

        $this->assertSame('myproject', $config->getProjectName());
        $this->assertSame(3, $config->getVersion());
    }

    public function testLoadProjectConfigFallsBackToLegacyDirWhenNotInLocal(): void
    {
        $this->writeConfigFile($this->legacyDir, 'myproject', 2);

        $config = $this->makeHandler()->loadProjectConfig('myproject');

        $this->assertSame(2, $config->getVersion());
    }

    public function testLoadProjectConfigPrefersLocalOverLegacyWhenBothExist(): void
    {
        $this->writeConfigFile($this->localDir, 'myproject', 3);
        $this->writeConfigFile($this->legacyDir, 'myproject', 2);

        $config = $this->makeHandler()->loadProjectConfig('myproject');

        $this->assertSame(3, $config->getVersion());
    }

    public function testLoadProjectConfigThrowsWhenNotFoundAnywhere(): void
    {
        $this->expectException(ProjectConfigNotFoundException::class);

        $this->makeHandler()->loadProjectConfig('does-not-exist');
    }

    public function testLoadProjectConfigThrowsWhenNotFoundAndNoLegacyHandlerConfigured(): void
    {
        $this->writeConfigFile($this->legacyDir, 'myproject', 2);

        $this->expectException(ProjectConfigNotFoundException::class);

        $this->makeHandler(withLegacy: false)->loadProjectConfig('myproject');
    }

    public function testPeekProjectConfigVersionFallsBackToLegacyDir(): void
    {
        $this->writeConfigFile($this->legacyDir, 'myproject', 2);

        $version = $this->makeHandler()->peekProjectConfigVersion('myproject');

        $this->assertSame(2, $version);
    }

    public function testLoadProjectConfigAutoMigratesLegacyProjectIntoLocalDir(): void
    {
        $this->writeConfigFile($this->legacyDir, 'myproject', 2);

        $migrationApplied = false;
        $migration = $this->makeMigration(2, ProjectConfiguration::CURRENT_VERSION, function (array $config) use (&$migrationApplied) {
            $migrationApplied = true;
            $config['version'] = ProjectConfiguration::CURRENT_VERSION;

            return $config;
        });

        $config = $this->makeHandlerWithMigrator([$migration])->loadProjectConfig('myproject');

        $this->assertTrue($migrationApplied);
        $this->assertSame(ProjectConfiguration::CURRENT_VERSION, $config->getVersion());
        $this->assertFileExists(sprintf('%s/myproject.json', $this->localDir));
        $this->assertFileDoesNotExist(sprintf('%s/myproject.json', $this->legacyDir));
    }

    public function testPeekProjectConfigVersionAutoMigratesLegacyProjectIntoLocalDir(): void
    {
        $this->writeConfigFile($this->legacyDir, 'myproject', 2);

        $migration = $this->makeMigration(2, ProjectConfiguration::CURRENT_VERSION, fn (array $config) => [...$config, 'version' => ProjectConfiguration::CURRENT_VERSION]);

        $version = $this->makeHandlerWithMigrator([$migration])->peekProjectConfigVersion('myproject');

        $this->assertSame(ProjectConfiguration::CURRENT_VERSION, $version);
        $this->assertFileExists(sprintf('%s/myproject.json', $this->localDir));
    }

    public function testLoadProjectConfigDoesNotAutoMigrateWhenAlreadyInLocalDir(): void
    {
        $this->writeConfigFile($this->localDir, 'myproject', ProjectConfiguration::CURRENT_VERSION);

        $migration = $this->createMock(ConfigMigration::class);
        $migration->expects($this->never())->method('migrate');

        $config = $this->makeHandlerWithMigrator([$migration])->loadProjectConfig('myproject');

        $this->assertSame(ProjectConfiguration::CURRENT_VERSION, $config->getVersion());
    }
}
