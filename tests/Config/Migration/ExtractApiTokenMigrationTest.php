<?php

namespace Ezdeliver\Tests\Config\Migration;

use Ezdeliver\Config\Migration\ExtractApiTokenMigration;
use Ezdeliver\Token\TokenVault;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class ExtractApiTokenMigrationTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;
    private TokenVault $tokenVault;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir().'/ez-delivery-extract-token-migration-test-'.uniqid();
        $this->fs->mkdir($this->tmpDir);
        $this->tokenVault = new TokenVault($this->fs, sprintf('%s/tokens.json', $this->tmpDir));
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    /**
     * @return array<string, mixed>
     */
    private function validConfigArray(): array
    {
        return [
            'projectName' => 'myproject',
            'src' => '/path/to/src',
            'baseBranch' => 'main',
            'repo' => ['type' => 'gitlab', 'namespace' => 'ns', 'name' => 'repo', 'apiToken' => 'glpat-abc123'],
            'envs' => [['name' => 'staging', 'alreadyDeliveredLabel' => 'delivered', 'toDeliverLabel' => 'to-deliver']],
            'version' => 1,
        ];
    }

    public function testReportsCorrectVersionRange(): void
    {
        $migration = new ExtractApiTokenMigration($this->tokenVault);

        $this->assertSame(1, $migration->getFromVersion());
        $this->assertSame(2, $migration->getToVersion());
    }

    public function testReusesExistingRefWhenTokenValueAlreadyInVault(): void
    {
        $this->tokenVault->set('gitlab-mycompany', 'glpat-abc123');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('ask');

        $result = (new ExtractApiTokenMigration($this->tokenVault))->migrate($this->validConfigArray(), $io);

        $this->assertSame('gitlab-mycompany', $result['repo']['apiTokenRef']);
        $this->assertArrayNotHasKey('apiToken', $result['repo']);
        $this->assertSame(['gitlab-mycompany'], $this->tokenVault->listRefs());
    }

    public function testPromptsForNewRefWhenTokenValueNotInVault(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('ask')->with('This token needs a reference name', 'gitlab-ns')->willReturn('gitlab-mycompany');
        $io->expects($this->never())->method('warning');

        $result = (new ExtractApiTokenMigration($this->tokenVault))->migrate($this->validConfigArray(), $io);

        $this->assertSame('gitlab-mycompany', $result['repo']['apiTokenRef']);
        $this->assertArrayNotHasKey('apiToken', $result['repo']);
        $this->assertSame('glpat-abc123', $this->tokenVault->get('gitlab-mycompany'));
    }

    public function testWarnsAndOverwritesWhenChosenRefNameAlreadyTakenByDifferentValue(): void
    {
        $this->tokenVault->set('gitlab-mycompany', 'glpat-old-different-value');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('ask')->willReturn('gitlab-mycompany');
        $io->expects($this->once())->method('warning');

        (new ExtractApiTokenMigration($this->tokenVault))->migrate($this->validConfigArray(), $io);

        $this->assertSame('glpat-abc123', $this->tokenVault->get('gitlab-mycompany'));
    }

    public function testBumpsConfigVersionToTargetVersion(): void
    {
        $this->tokenVault->set('gitlab-mycompany', 'glpat-abc123');

        $io = $this->createMock(SymfonyStyle::class);

        $result = (new ExtractApiTokenMigration($this->tokenVault))->migrate($this->validConfigArray(), $io);

        $this->assertSame(2, $result['version']);
    }
}
