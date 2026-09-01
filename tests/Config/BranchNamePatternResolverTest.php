<?php

namespace Ezdeliver\Tests\Config;

use Ezdeliver\Config\BranchNamePatternResolver;
use Ezdeliver\Config\Model\ProjectEnvConfig;
use PHPUnit\Framework\TestCase;

class BranchNamePatternResolverTest extends TestCase
{
    private const string DATE = '2026-01-01T15:05:23+00:00';

    public function testResolveReplacesEnvVariable(): void
    {
        $result = (new BranchNamePatternResolver())->resolve('%env%-release', 'recette', new \DateTimeImmutable(self::DATE));

        $this->assertSame('recette-release', $result);
    }

    public function testResolveReplacesDateVariable(): void
    {
        $result = (new BranchNamePatternResolver())->resolve('recette-%date%', 'recette', new \DateTimeImmutable(self::DATE));

        $this->assertSame('recette-2026-01-01', $result);
    }

    public function testResolveReplacesDateTimeVariable(): void
    {
        $result = (new BranchNamePatternResolver())->resolve('recette-%date_time%', 'recette', new \DateTimeImmutable(self::DATE));

        $this->assertSame('recette-2026-01-01_15-05', $result);
    }

    public function testResolveReplacesDateFullVariable(): void
    {
        $result = (new BranchNamePatternResolver())->resolve('recette-%date_full%', 'recette', new \DateTimeImmutable(self::DATE));

        $this->assertSame('recette-2026-01-01_15-05-23', $result);
    }

    public function testResolveLeavesUnknownTokensAndStrayPercentUntouched(): void
    {
        $result = (new BranchNamePatternResolver())->resolve('recette-100%-done', 'recette', new \DateTimeImmutable(self::DATE));

        $this->assertSame('recette-100%-done', $result);
    }

    public function testResolveDefaultBranchNameUsesEnvPattern(): void
    {
        $env = new ProjectEnvConfig('recette', 'delivered', 'to-deliver', '%env%-%date%', false);

        $result = (new BranchNamePatternResolver())->resolveDefaultBranchName($env, new \DateTimeImmutable(self::DATE));

        $this->assertSame('recette-2026-01-01', $result);
    }

    public function testValidateAcceptsSupportedVariables(): void
    {
        $this->expectNotToPerformAssertions();

        (new BranchNamePatternResolver())->validate('%env%-%date%-%date_time%-%date_full%');
    }

    public function testValidateAcceptsPatternWithoutAnyVariable(): void
    {
        $this->expectNotToPerformAssertions();

        (new BranchNamePatternResolver())->validate('fixed-branch-name');
    }

    public function testValidateAcceptsStrayPercentSign(): void
    {
        $this->expectNotToPerformAssertions();

        (new BranchNamePatternResolver())->validate('recette-100%-done');
    }

    public function testValidateThrowsOnUnknownVariable(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown branch name pattern variable(s): %foo%. Supported variables: %env%, %date%, %date_time%, %date_full%');

        (new BranchNamePatternResolver())->validate('recette-%foo%');
    }

    public function testIsStaticReturnsTrueForPatternWithoutDateVariables(): void
    {
        $resolver = new BranchNamePatternResolver();

        $this->assertTrue($resolver->isStatic('titi'));
        $this->assertTrue($resolver->isStatic('%env%_toto'));
        $this->assertTrue($resolver->isStatic('%env%'));
    }

    public function testIsStaticReturnsFalseForPatternWithADateVariable(): void
    {
        $resolver = new BranchNamePatternResolver();

        $this->assertFalse($resolver->isStatic('%date%'));
        $this->assertFalse($resolver->isStatic('%date_time%-toto'));
        $this->assertFalse($resolver->isStatic('%date_full%-%env%'));
    }
}
