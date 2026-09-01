<?php

namespace Ezdeliver\Tests\Config\Migration;

use Ezdeliver\Config\Migration\AddDeleteCurrentEnvReleaseBranchMigration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class AddDeleteCurrentEnvReleaseBranchMigrationTest extends TestCase
{
    public function testReportsCorrectVersionRange(): void
    {
        $migration = new AddDeleteCurrentEnvReleaseBranchMigration();

        $this->assertSame(4, $migration->getFromVersion());
        $this->assertSame(5, $migration->getToVersion());
    }

    public function testMigrateBackfillsDeleteCurrentEnvReleaseBranchOnEnvsMissingIt(): void
    {
        $config = [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiTokenRef' => 'token-ref'],
            'envs' => [['name' => 'staging', 'alreadyDeliveredLabel' => 'delivered', 'toDeliverLabel' => 'to-deliver', 'branchNamePattern' => '%env%-%date_full%']],
            'version' => 4,
        ];

        $result = (new AddDeleteCurrentEnvReleaseBranchMigration())->migrate($config, $this->createMock(SymfonyStyle::class));

        $this->assertSame(5, $result['version']);
        $this->assertFalse($result['envs'][0]['deleteCurrentEnvReleaseBranch']);
    }

    public function testMigratePreservesAnAlreadySetValue(): void
    {
        $config = [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiTokenRef' => 'token-ref'],
            'envs' => [['name' => 'staging', 'alreadyDeliveredLabel' => 'delivered', 'toDeliverLabel' => 'to-deliver', 'branchNamePattern' => 'recette', 'deleteCurrentEnvReleaseBranch' => true]],
            'version' => 4,
        ];

        $result = (new AddDeleteCurrentEnvReleaseBranchMigration())->migrate($config, $this->createMock(SymfonyStyle::class));

        $this->assertTrue($result['envs'][0]['deleteCurrentEnvReleaseBranch']);
    }
}
