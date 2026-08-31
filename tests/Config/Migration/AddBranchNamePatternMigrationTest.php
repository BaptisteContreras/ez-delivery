<?php

namespace Ezdeliver\Tests\Config\Migration;

use Ezdeliver\Config\Migration\AddBranchNamePatternMigration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class AddBranchNamePatternMigrationTest extends TestCase
{
    public function testReportsCorrectVersionRange(): void
    {
        $migration = new AddBranchNamePatternMigration();

        $this->assertSame(3, $migration->getFromVersion());
        $this->assertSame(4, $migration->getToVersion());
    }

    public function testMigrateBackfillsDefaultBranchNamePatternOnEnvsMissingIt(): void
    {
        $config = [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiTokenRef' => 'token-ref'],
            'envs' => [['name' => 'staging', 'alreadyDeliveredLabel' => 'delivered', 'toDeliverLabel' => 'to-deliver']],
            'version' => 3,
        ];

        $result = (new AddBranchNamePatternMigration())->migrate($config, $this->createMock(SymfonyStyle::class));

        $this->assertSame(4, $result['version']);
        $this->assertSame('myproject', $result['projectName']);
        $this->assertSame('%env%-%date_full%', $result['envs'][0]['branchNamePattern']);
    }

    public function testMigratePreservesAnAlreadySetBranchNamePattern(): void
    {
        $config = [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiTokenRef' => 'token-ref'],
            'envs' => [['name' => 'staging', 'alreadyDeliveredLabel' => 'delivered', 'toDeliverLabel' => 'to-deliver', 'branchNamePattern' => '%env%-%date%']],
            'version' => 3,
        ];

        $result = (new AddBranchNamePatternMigration())->migrate($config, $this->createMock(SymfonyStyle::class));

        $this->assertSame('%env%-%date%', $result['envs'][0]['branchNamePattern']);
    }
}
