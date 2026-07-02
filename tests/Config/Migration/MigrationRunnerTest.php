<?php

namespace Ezdeliver\Tests\Config\Migration;

use Ezdeliver\Config\Migration\ConfigMigration;
use Ezdeliver\Config\Migration\MigrationNotFoundException;
use Ezdeliver\Config\Migration\MigrationRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationRunnerTest extends TestCase
{
    private function makeMigration(int $from, int $to, callable $transform): ConfigMigration
    {
        $migration = $this->createMock(ConfigMigration::class);
        $migration->method('getFromVersion')->willReturn($from);
        $migration->method('getToVersion')->willReturn($to);
        $migration->method('migrate')->willReturnCallback($transform);

        return $migration;
    }

    public function testMigrateAppliesSingleStep(): void
    {
        $migration = $this->makeMigration(1, 2, fn (array $config) => [...$config, 'step2' => true]);
        $runner = new MigrationRunner([$migration]);

        $result = $runner->migrate(['field' => 'value'], 1, 2, $this->createMock(SymfonyStyle::class));

        $this->assertSame(['field' => 'value', 'step2' => true], $result);
    }

    public function testMigrateChainsThroughMultipleSteps(): void
    {
        $v1ToV2 = $this->makeMigration(1, 2, fn (array $config) => [...$config, 'step2' => true]);
        $v2ToV3 = $this->makeMigration(2, 3, fn (array $config) => [...$config, 'step3' => true]);
        $runner = new MigrationRunner([$v1ToV2, $v2ToV3]);

        $result = $runner->migrate(['field' => 'value'], 1, 3, $this->createMock(SymfonyStyle::class));

        $this->assertSame(['field' => 'value', 'step2' => true, 'step3' => true], $result);
    }

    public function testMigrateStopsAtTargetVersionEvenIfMoreMigrationsAreRegistered(): void
    {
        $v1ToV2 = $this->makeMigration(1, 2, fn (array $config) => [...$config, 'step2' => true]);
        $v2ToV3 = $this->makeMigration(2, 3, fn (array $config) => [...$config, 'step3' => true]);
        $runner = new MigrationRunner([$v1ToV2, $v2ToV3]);

        $result = $runner->migrate(['field' => 'value'], 1, 2, $this->createMock(SymfonyStyle::class));

        $this->assertSame(['field' => 'value', 'step2' => true], $result);
    }

    public function testMigratePassesIoToEachMigration(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $capturedIo = null;

        $migration = $this->createMock(ConfigMigration::class);
        $migration->method('getFromVersion')->willReturn(1);
        $migration->method('getToVersion')->willReturn(2);
        $migration->method('migrate')->willReturnCallback(function (array $config, SymfonyStyle $passedIo) use (&$capturedIo) {
            $capturedIo = $passedIo;

            return $config;
        });

        $runner = new MigrationRunner([$migration]);
        $runner->migrate([], 1, 2, $io);

        $this->assertSame($io, $capturedIo);
    }

    public function testMigrateThrowsWhenNoMigrationMatchesCurrentVersion(): void
    {
        $runner = new MigrationRunner([]);

        $this->expectException(MigrationNotFoundException::class);

        $runner->migrate([], 1, 2, $this->createMock(SymfonyStyle::class));
    }

    public function testMigrateReturnsConfigUnchangedWhenAlreadyAtTargetVersion(): void
    {
        $runner = new MigrationRunner([]);

        $result = $runner->migrate(['field' => 'value'], 2, 2, $this->createMock(SymfonyStyle::class));

        $this->assertSame(['field' => 'value'], $result);
    }
}
