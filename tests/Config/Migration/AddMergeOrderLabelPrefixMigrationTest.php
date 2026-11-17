<?php

namespace Ezdeliver\Tests\Config\Migration;

use Ezdeliver\Config\Migration\AddMergeOrderLabelPrefixMigration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class AddMergeOrderLabelPrefixMigrationTest extends TestCase
{
    private function makeConfig(array $extra = []): array
    {
        return [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiTokenRef' => 'token-ref'],
            'envs' => [],
            'protectedBranches' => ['main'],
            'version' => 6,
            ...$extra,
        ];
    }

    public function testReportsCorrectVersionRange(): void
    {
        $migration = new AddMergeOrderLabelPrefixMigration();

        $this->assertSame(6, $migration->getFromVersion());
        $this->assertSame(7, $migration->getToVersion());
    }

    public function testBackfillsDefaultPrefixWhenMissing(): void
    {
        $result = (new AddMergeOrderLabelPrefixMigration())->migrate($this->makeConfig(), $this->createMock(SymfonyStyle::class));

        $this->assertSame(7, $result['version']);
        $this->assertSame('merge-order::', $result['mergeOrderLabelPrefix']);
    }

    public function testPreservesExistingPrefix(): void
    {
        $result = (new AddMergeOrderLabelPrefixMigration())->migrate(
            $this->makeConfig(['mergeOrderLabelPrefix' => 'prio/']),
            $this->createMock(SymfonyStyle::class)
        );

        $this->assertSame('prio/', $result['mergeOrderLabelPrefix']);
    }

    public function testBackfillsSortMrDisabledWhenMissing(): void
    {
        $result = (new AddMergeOrderLabelPrefixMigration())->migrate($this->makeConfig(), $this->createMock(SymfonyStyle::class));

        $this->assertFalse($result['enableSortMr']);
    }

    public function testPreservesExistingSortMrFlag(): void
    {
        $result = (new AddMergeOrderLabelPrefixMigration())->migrate(
            $this->makeConfig(['enableSortMr' => true]),
            $this->createMock(SymfonyStyle::class)
        );

        $this->assertTrue($result['enableSortMr']);
    }
}
