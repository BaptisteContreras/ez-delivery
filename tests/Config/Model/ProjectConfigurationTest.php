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
        "repo": {"type": "gitlab", "namespace": "ns", "name": "repo", "apiTokenRef": "token-ref"},
        "envs": [{"name": "staging", "alreadyDeliveredLabel": "delivered", "toDeliverLabel": "to-deliver", "branchNamePattern": "%env%-%date_full%", "deleteCurrentEnvReleaseBranch": false}],
        "protectedBranches": ["master", "main"]
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
        $config = new ProjectConfiguration('name', 'src', 'main', $this->createMock(ProjectRepoConfig::class), [], [], 3);

        $this->assertSame(3, $config->getVersion());
    }

    public function testDeserializingConfigWithoutMergeOrderPrefixUsesDefault(): void
    {
        $serializer = (new SfFactory())->createSfSerializer();

        $config = $serializer->deserialize(self::FIXTURE_WITHOUT_VERSION, ProjectConfiguration::class, 'json');

        $this->assertSame('merge-order::', $config->getMergeOrderLabelPrefix());
    }

    public function testSortMrIsDisabledByDefault(): void
    {
        $config = (new SfFactory())->createSfSerializer()->deserialize(self::FIXTURE_WITHOUT_VERSION, ProjectConfiguration::class, 'json');

        $this->assertFalse($config->isEnableSortMr());
    }

    public function testSortMrRoundTripsThroughSerializer(): void
    {
        $serializer = (new SfFactory())->createSfSerializer();
        $data = json_decode(self::FIXTURE_WITHOUT_VERSION, true);
        $data['enableSortMr'] = true;

        $config = $serializer->deserialize(json_encode($data), ProjectConfiguration::class, 'json');

        $this->assertTrue($config->isEnableSortMr());
        $this->assertTrue(json_decode($serializer->serialize($config, 'json'), true)['enableSortMr']);
    }
}
