<?php

namespace Ezdeliver\Tests\Repo;

use Ezdeliver\Config\Model\GithubRepoConfig;
use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Repo\GithubDriver;
use Ezdeliver\Repo\GitlabLabelResolver;
use Ezdeliver\Repo\GitlabLinkedIssueDriver;
use Ezdeliver\Repo\GitlabMrLabelDriver;
use Ezdeliver\Token\TokenVault;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class GitlabDriverSupportTest extends TestCase
{
    private function makeTokenVault(): TokenVault
    {
        return new TokenVault(new Filesystem(), sys_get_temp_dir().'/ez-delivery-driver-support-test-unused.json');
    }

    private function makeLinkedIssueDriver(): GitlabLinkedIssueDriver
    {
        $io = $this->createMock(SymfonyStyle::class);
        $tokenVault = $this->makeTokenVault();
        $resolver = new GitlabLabelResolver($io, $tokenVault);

        return new GitlabLinkedIssueDriver($io, $resolver, $tokenVault);
    }

    private function makeMrLabelDriver(): GitlabMrLabelDriver
    {
        $io = $this->createMock(SymfonyStyle::class);
        $tokenVault = $this->makeTokenVault();
        $resolver = new GitlabLabelResolver($io, $tokenVault);

        return new GitlabMrLabelDriver($io, $resolver, $tokenVault);
    }

    private function makeGitlabConfig(PrSelectionMode $mode): GitlabRepoConfig
    {
        return new GitlabRepoConfig('ns', 'repo', 'token-ref', $mode);
    }

    public function testLinkedIssueDriverSupportsOnlyLinkedIssueMode(): void
    {
        $driver = $this->makeLinkedIssueDriver();

        $this->assertTrue($driver->support($this->makeGitlabConfig(PrSelectionMode::LinkedIssue)));
        $this->assertFalse($driver->support($this->makeGitlabConfig(PrSelectionMode::MrLabel)));
    }

    public function testMrLabelDriverSupportsOnlyMrLabelMode(): void
    {
        $driver = $this->makeMrLabelDriver();

        $this->assertTrue($driver->support($this->makeGitlabConfig(PrSelectionMode::MrLabel)));
        $this->assertFalse($driver->support($this->makeGitlabConfig(PrSelectionMode::LinkedIssue)));
    }

    public function testNeitherGitlabDriverSupportsGithubConfig(): void
    {
        $githubConfig = new GithubRepoConfig('owner', 'repo', 'token-ref');

        $this->assertFalse($this->makeLinkedIssueDriver()->support($githubConfig));
        $this->assertFalse($this->makeMrLabelDriver()->support($githubConfig));
    }

    public function testGithubDriverDoesNotSupportGitlabConfigRegardlessOfMode(): void
    {
        $driver = new GithubDriver($this->createMock(SymfonyStyle::class), $this->makeTokenVault());

        $this->assertFalse($driver->support($this->makeGitlabConfig(PrSelectionMode::LinkedIssue)));
        $this->assertFalse($driver->support($this->makeGitlabConfig(PrSelectionMode::MrLabel)));
    }
}
