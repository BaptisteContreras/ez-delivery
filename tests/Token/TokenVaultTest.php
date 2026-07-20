<?php

namespace Ezdeliver\Tests\Token;

use Ezdeliver\Token\TokenNotFoundException;
use Ezdeliver\Token\TokenVault;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TokenVaultTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;
    private string $vaultFilePath;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir().'/ez-delivery-token-vault-test-'.uniqid();
        $this->fs->mkdir($this->tmpDir);
        $this->vaultFilePath = sprintf('%s/tokens.json', $this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    private function makeVault(): TokenVault
    {
        return new TokenVault($this->fs, $this->vaultFilePath);
    }

    public function testGetThrowsWhenRefDoesNotExist(): void
    {
        $this->expectException(TokenNotFoundException::class);

        $this->makeVault()->get('missing-ref');
    }

    public function testSetThenGetReturnsStoredValue(): void
    {
        $vault = $this->makeVault();
        $vault->set('gitlab-mycompany', 'glpat-abc123');

        $this->assertSame('glpat-abc123', $vault->get('gitlab-mycompany'));
    }

    public function testSetCreatesVaultFileOnFirstWrite(): void
    {
        $this->assertFileDoesNotExist($this->vaultFilePath);

        $this->makeVault()->set('gitlab-mycompany', 'glpat-abc123');

        $this->assertFileExists($this->vaultFilePath);
    }

    public function testSetOverwritesExistingEntry(): void
    {
        $vault = $this->makeVault();
        $vault->set('gitlab-mycompany', 'glpat-old');
        $vault->set('gitlab-mycompany', 'glpat-new');

        $this->assertSame('glpat-new', $vault->get('gitlab-mycompany'));
    }

    public function testHasReturnsTrueOnlyForExistingRef(): void
    {
        $vault = $this->makeVault();
        $vault->set('gitlab-mycompany', 'glpat-abc123');

        $this->assertTrue($vault->has('gitlab-mycompany'));
        $this->assertFalse($vault->has('missing-ref'));
    }

    public function testFindRefByValueReturnsMatchingRefName(): void
    {
        $vault = $this->makeVault();
        $vault->set('gitlab-mycompany', 'glpat-abc123');
        $vault->set('github-personal', 'ghp-xyz789');

        $this->assertSame('gitlab-mycompany', $vault->findRefByValue('glpat-abc123'));
    }

    public function testFindRefByValueReturnsNullWhenNoMatch(): void
    {
        $vault = $this->makeVault();
        $vault->set('gitlab-mycompany', 'glpat-abc123');

        $this->assertNull($vault->findRefByValue('does-not-exist'));
    }

    public function testListRefsReturnsAllStoredNames(): void
    {
        $vault = $this->makeVault();
        $vault->set('gitlab-mycompany', 'glpat-abc123');
        $vault->set('github-personal', 'ghp-xyz789');

        $this->assertSame(['gitlab-mycompany', 'github-personal'], $vault->listRefs());
    }

    public function testListRefsReturnsEmptyArrayWhenVaultFileDoesNotExist(): void
    {
        $this->assertSame([], $this->makeVault()->listRefs());
    }
}
