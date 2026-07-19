<?php

namespace Ezdeliver\Tests\Config\Model;

use Ezdeliver\Config\Model\GitlabRepoConfig;
use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Factory\SfFactory;
use PHPUnit\Framework\TestCase;

class GitlabRepoConfigTest extends TestCase
{
    private const string FIXTURE_WITHOUT_MODE = '{
        "projectName": "test-project",
        "src": "/path/to/src",
        "baseBranch": "main",
        "repo": {"type": "gitlab", "namespace": "ns", "name": "repo", "apiToken": "token"},
        "envs": [{"name": "staging", "alreadyDeliveredLabel": "delivered", "toDeliverLabel": "to-deliver"}]
    }';

    public function testDeserializingRepoConfigWithoutModeDefaultsToLinkedIssue(): void
    {
        $serializer = (new SfFactory())->createSfSerializer();

        $config = $serializer->deserialize(self::FIXTURE_WITHOUT_MODE, ProjectConfiguration::class, 'json');

        $repo = $config->getRepo();
        $this->assertInstanceOf(GitlabRepoConfig::class, $repo);
        $this->assertSame(PrSelectionMode::LinkedIssue, $repo->getMode());
    }

    public function testDeserializingRepoConfigWithExplicitMode(): void
    {
        $serializer = (new SfFactory())->createSfSerializer();
        $data = json_decode(self::FIXTURE_WITHOUT_MODE, true);
        $data['repo']['mode'] = PrSelectionMode::MrLabel->value;

        $config = $serializer->deserialize(json_encode($data), ProjectConfiguration::class, 'json');

        $repo = $config->getRepo();
        $this->assertInstanceOf(GitlabRepoConfig::class, $repo);
        $this->assertSame(PrSelectionMode::MrLabel, $repo->getMode());
    }

    public function testGetModeReturnsConstructorValue(): void
    {
        $config = new GitlabRepoConfig('ns', 'repo', 'token', PrSelectionMode::MrLabel);

        $this->assertSame(PrSelectionMode::MrLabel, $config->getMode());
    }

    public function testGetModeDefaultsToLinkedIssueWhenNotProvided(): void
    {
        $config = new GitlabRepoConfig('ns', 'repo', 'token');

        $this->assertSame(PrSelectionMode::LinkedIssue, $config->getMode());
    }
}
