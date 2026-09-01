<?php

namespace Ezdeliver\Tests\Config\Migration;

use Ezdeliver\Config\Migration\AddProtectedBranchesMigration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class AddProtectedBranchesMigrationTest extends TestCase
{
    public function testReportsCorrectVersionRange(): void
    {
        $migration = new AddProtectedBranchesMigration();

        $this->assertSame(5, $migration->getFromVersion());
        $this->assertSame(6, $migration->getToVersion());
    }

    public function testMigrateBackfillsDefaultProtectedBranchesWhenMissing(): void
    {
        $config = [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiTokenRef' => 'token-ref'],
            'envs' => [],
            'version' => 5,
        ];

        $result = (new AddProtectedBranchesMigration())->migrate($config, $this->createMock(SymfonyStyle::class));

        $this->assertSame(6, $result['version']);
        $this->assertSame(['master', 'main', 'develop', 'integration'], $result['protectedBranches']);
    }

    public function testMigratePreservesAnAlreadySetList(): void
    {
        $config = [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiTokenRef' => 'token-ref'],
            'envs' => [],
            'protectedBranches' => ['prod'],
            'version' => 5,
        ];

        $result = (new AddProtectedBranchesMigration())->migrate($config, $this->createMock(SymfonyStyle::class));

        $this->assertSame(['prod'], $result['protectedBranches']);
    }
}
