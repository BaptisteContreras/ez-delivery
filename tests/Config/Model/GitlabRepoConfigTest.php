<?php

namespace Ezdeliver\Tests\Config\Model;

use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Factory\SfFactory;
use PHPUnit\Framework\TestCase;

class GitlabRepoConfigTest extends TestCase
{
    public function testDeserializingConfigWithoutModeDefaultsToLinkedIssue(): void
    {
        $serializer = (new SfFactory())->createSfSerializer();

        $json = json_encode([
            'namespace' => 'my-namespace',
            'name' => 'my-repo',
            'apiToken' => 'token',
        ]);

        $config = $serializer->deserialize($json, GitlabRepoConfig::class, 'json');

        $this->assertSame(PrSelectionMode::LinkedIssue, $config->getMode());
    }

    public function testDeserializingConfigWithExplicitMrLabelMode(): void
    {
        $serializer = (new SfFactory())->createSfSerializer();

        $json = json_encode([
            'namespace' => 'my-namespace',
            'name' => 'my-repo',
            'apiToken' => 'token',
            'mode' => PrSelectionMode::MrLabel->value,
        ]);

        $config = $serializer->deserialize($json, GitlabRepoConfig::class, 'json');

        $this->assertSame(PrSelectionMode::MrLabel, $config->getMode());
    }

    public function testConstructorDefaultsToLinkedIssueMode(): void
    {
        $config = new GitlabRepoConfig('ns', 'repo', 'token');

        $this->assertSame(PrSelectionMode::LinkedIssue, $config->getMode());
    }
}
