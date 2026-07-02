<?php

namespace Ezdeliver\Tests\Repo;

use Ezdeliver\Config\Model\GithubRepoConfig;
use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Repo\GithubDriver;
use Ezdeliver\Repo\GitlabLabelResolver;
use Ezdeliver\Repo\GitlabLinkedIssueDriver;
use Ezdeliver\Repo\GitlabMrLabelDriver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class GitlabDriverSupportTest extends TestCase
{
    private function makeGitlabConfig(PrSelectionMode $mode): GitlabRepoConfig
    {
        return new GitlabRepoConfig('namespace', 'repo', 'token', $mode);
    }

    private function makeGithubConfig(): GithubRepoConfig
    {
        return new GithubRepoConfig('owner', 'repo', 'token');
    }

    private function makeLabelResolver(): GitlabLabelResolver
    {
        return new GitlabLabelResolver($this->createMock(SymfonyStyle::class));
    }

    private function makeLinkedIssueDriver(): GitlabLinkedIssueDriver
    {
        return new GitlabLinkedIssueDriver($this->createMock(SymfonyStyle::class), $this->makeLabelResolver());
    }

    private function makeMrLabelDriver(): GitlabMrLabelDriver
    {
        return new GitlabMrLabelDriver($this->createMock(SymfonyStyle::class), $this->makeLabelResolver());
    }

    public function testLinkedIssueDriverSupportsGitlabConfigInLinkedIssueMode(): void
    {
        $this->assertTrue($this->makeLinkedIssueDriver()->support($this->makeGitlabConfig(PrSelectionMode::LinkedIssue)));
    }

    public function testLinkedIssueDriverDoesNotSupportGitlabConfigInMrLabelMode(): void
    {
        $this->assertFalse($this->makeLinkedIssueDriver()->support($this->makeGitlabConfig(PrSelectionMode::MrLabel)));
    }

    public function testLinkedIssueDriverDoesNotSupportGithubConfig(): void
    {
        $this->assertFalse($this->makeLinkedIssueDriver()->support($this->makeGithubConfig()));
    }

    public function testMrLabelDriverSupportsGitlabConfigInMrLabelMode(): void
    {
        $this->assertTrue($this->makeMrLabelDriver()->support($this->makeGitlabConfig(PrSelectionMode::MrLabel)));
    }

    public function testMrLabelDriverDoesNotSupportGitlabConfigInLinkedIssueMode(): void
    {
        $this->assertFalse($this->makeMrLabelDriver()->support($this->makeGitlabConfig(PrSelectionMode::LinkedIssue)));
    }

    public function testMrLabelDriverDoesNotSupportGithubConfig(): void
    {
        $this->assertFalse($this->makeMrLabelDriver()->support($this->makeGithubConfig()));
    }

    public function testGithubDriverDoesNotSupportGitlabConfigRegardlessOfMode(): void
    {
        $driver = new GithubDriver($this->createMock(SymfonyStyle::class));

        $this->assertFalse($driver->support($this->makeGitlabConfig(PrSelectionMode::LinkedIssue)));
        $this->assertFalse($driver->support($this->makeGitlabConfig(PrSelectionMode::MrLabel)));
    }

    public function testLinkedIssueDriverAndMrLabelDriverBothSupportLabelsUpdate(): void
    {
        $this->assertTrue($this->makeLinkedIssueDriver()->supportLabelsUpdate());
        $this->assertTrue($this->makeMrLabelDriver()->supportLabelsUpdate());
    }

    public function testLinkedIssueDriverReferenceStrategySupportsReference(): void
    {
        $this->assertTrue($this->makeLinkedIssueDriver()->getPrReferenceStrategy()->supportsReference());
    }

    public function testMrLabelDriverReferenceStrategyDoesNotSupportReference(): void
    {
        $this->assertFalse($this->makeMrLabelDriver()->getPrReferenceStrategy()->supportsReference());
    }
}
