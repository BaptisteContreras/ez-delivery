<?php

namespace Ezdeliver\Tests\Config;

use Ezdeliver\Config\Migration\ConfigMigration;
use Ezdeliver\Config\Migration\MigrationRunner;
use Ezdeliver\Config\Migrator;
use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\ProjectConfigNotFoundException;
use Ezdeliver\Config\StorageHandler;
use Ezdeliver\Factory\SfFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class MigratorTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir().'/ez-delivery-migrator-test-'.uniqid();
        $this->fs->mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    private function writeConfigFile(string $projectName, array $data): void
    {
        $this->fs->dumpFile(sprintf('%s/%s.json', $this->tmpDir, $projectName), json_encode($data));
    }

    private function readConfigFile(string $projectName): array
    {
        return json_decode(file_get_contents(sprintf('%s/%s.json', $this->tmpDir, $projectName)), true);
    }

    private function validConfigData(int $version): array
    {
        return [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiToken' => 'token'],
            'envs' => [['name' => 'staging', 'alreadyDeliveredLabel' => 'delivered', 'toDeliverLabel' => 'to-deliver']],
            'version' => $version,
        ];
    }

    /**
     * @param array<ConfigMigration> $migrations
     */
    private function makeMigrator(array $migrations, ?SymfonyStyle $io = null): Migrator
    {
        $io ??= $this->createMock(SymfonyStyle::class);
        $serializer = (new SfFactory())->createSfSerializer();

        return new Migrator(
            new StorageHandler($io, $this->fs, $serializer, $this->tmpDir),
            new MigrationRunner($migrations),
            $serializer,
            $io,
        );
    }

    private function makeMigration(int $from, int $to, callable $transform): ConfigMigration
    {
        $migration = $this->createMock(ConfigMigration::class);
        $migration->method('getFromVersion')->willReturn($from);
        $migration->method('getToVersion')->willReturn($to);
        $migration->method('migrate')->willReturnCallback($transform);

        return $migration;
    }

    public function testMigrateProjectConfigUpgradesFileBackupsAndSaves(): void
    {
        // CURRENT_VERSION is 1 today (no real migrations shipped yet), so this test uses a
        // synthetic "version 0" fixture plus a test-only 0->CURRENT_VERSION migration purely
        // to exercise the upgrade path end-to-end. Production never registers a migration
        // this way — ConfigHandlerFactory::createMigrationRunner() ships an empty list.
        $this->writeConfigFile('myproject', array_merge($this->validConfigData(1), ['version' => 0]));

        $migrationApplied = false;
        $migration = $this->makeMigration(0, ProjectConfiguration::CURRENT_VERSION, function (array $config) use (&$migrationApplied) {
            $migrationApplied = true;
            $config['version'] = ProjectConfiguration::CURRENT_VERSION;

            return $config;
        });

        $result = $this->makeMigrator([$migration])->migrateProjectConfig('myproject');

        $this->assertSame(0, $result);
        $this->assertTrue($migrationApplied);

        $saved = $this->readConfigFile('myproject');
        $this->assertSame(ProjectConfiguration::CURRENT_VERSION, $saved['version']);

        $backupPath = sprintf('%s/myproject.json.bak', $this->tmpDir);
        $this->assertFileExists($backupPath);
        $backedUp = json_decode(file_get_contents($backupPath), true);
        $this->assertSame(0, $backedUp['version']);
    }

    public function testMigrateProjectConfigDoesNothingWhenAlreadyAtCurrentVersion(): void
    {
        $this->writeConfigFile('myproject', $this->validConfigData(ProjectConfiguration::CURRENT_VERSION));

        $result = $this->makeMigrator([])->migrateProjectConfig('myproject');

        $this->assertSame(0, $result);
        $this->assertFileDoesNotExist(sprintf('%s/myproject.json.bak', $this->tmpDir));

        $saved = $this->readConfigFile('myproject');
        $this->assertSame(ProjectConfiguration::CURRENT_VERSION, $saved['version']);
    }

    public function testMigrateProjectConfigReturnsErrorWhenConfigIsNewerThanSupported(): void
    {
        $this->writeConfigFile('myproject', $this->validConfigData(ProjectConfiguration::CURRENT_VERSION + 1));

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error');

        $result = $this->makeMigrator([], $io)->migrateProjectConfig('myproject');

        $this->assertSame(1, $result);
        $this->assertFileDoesNotExist(sprintf('%s/myproject.json.bak', $this->tmpDir));
    }

    public function testMigrateProjectConfigThrowsWhenProjectDoesNotExist(): void
    {
        $this->expectException(ProjectConfigNotFoundException::class);

        $this->makeMigrator([])->migrateProjectConfig('does-not-exist');
    }
}
