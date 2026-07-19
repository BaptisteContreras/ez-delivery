<?php

namespace Ezdeliver\Tests\Config\Model;

use Ezdeliver\Config\Model\GithubRepoConfig;
use Ezdeliver\Config\Model\PrSelectionMode;
use PHPUnit\Framework\TestCase;

class GithubRepoConfigTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $config = new GithubRepoConfig('owner', 'repo', 'token');

        $this->assertSame('owner', $config->getOwner());
        $this->assertSame('repo', $config->getName());
        $this->assertSame('token', $config->getApiToken());
        $this->assertSame(GithubRepoConfig::TYPE, $config->getType());
    }

    public function testGetModeAlwaysReturnsLinkedIssue(): void
    {
        $config = new GithubRepoConfig('owner', 'repo', 'token');

        $this->assertSame(PrSelectionMode::LinkedIssue, $config->getMode());
    }
}
