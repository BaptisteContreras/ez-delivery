<?php

namespace Ezdeliver\Tests\Config\Model;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Factory\SfFactory;
use PHPUnit\Framework\TestCase;

class ProjectConfigurationTest extends TestCase
{
    private const string FIXTURE_WITHOUT_VERSION = '{
        "projectName": "test-project",
        "src": "/path/to/src",
        "baseBranch": "main",
        "repo": {"type": "gitlab", "namespace": "ns", "name": "repo", "apiToken": "token"},
        "envs": [{"name": "staging", "alreadyDeliveredLabel": "delivered", "toDeliverLabel": "to-deliver"}]
    }';

    public function testDeserializingConfigWithoutVersionDefaultsToInitialVersion(): void
    {
        $serializer = (new SfFactory())->createSfSerializer();

        $config = $serializer->deserialize(self::FIXTURE_WITHOUT_VERSION, ProjectConfiguration::class, 'json');

        $this->assertSame(ProjectConfiguration::INITIAL_VERSION, $config->getVersion());
    }

    public function testDeserializingConfigWithExplicitVersion(): void
    {
        $serializer = (new SfFactory())->createSfSerializer();
        $data = json_decode(self::FIXTURE_WITHOUT_VERSION, true);
        $data['version'] = 2;

        $config = $serializer->deserialize(json_encode($data), ProjectConfiguration::class, 'json');

        $this->assertSame(2, $config->getVersion());
    }

    public function testGetVersionReturnsConstructorValue(): void
    {
        $config = new ProjectConfiguration('name', 'src', 'main', $this->createMock(ProjectRepoConfig::class), [], 3);

        $this->assertSame(3, $config->getVersion());
    }
}
