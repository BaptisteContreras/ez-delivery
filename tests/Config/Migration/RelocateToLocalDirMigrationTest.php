<?php

namespace Ezdeliver\Tests\Config\Migration;

use Ezdeliver\Config\Migration\RelocateToLocalDirMigration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class RelocateToLocalDirMigrationTest extends TestCase
{
    public function testReportsCorrectVersionRange(): void
    {
        $migration = new RelocateToLocalDirMigration();

        $this->assertSame(2, $migration->getFromVersion());
        $this->assertSame(3, $migration->getToVersion());
    }

    public function testMigrateOnlyBumpsTheVersionField(): void
    {
        $config = [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiTokenRef' => 'token-ref'],
            'envs' => [['name' => 'staging', 'alreadyDeliveredLabel' => 'delivered', 'toDeliverLabel' => 'to-deliver']],
            'version' => 2,
        ];

        $result = (new RelocateToLocalDirMigration())->migrate($config, $this->createMock(SymfonyStyle::class));

        $this->assertSame(3, $result['version']);
        $this->assertSame('myproject', $result['projectName']);
        $this->assertSame($config['repo'], $result['repo']);
    }
}
