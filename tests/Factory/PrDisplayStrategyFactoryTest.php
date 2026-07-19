<?php

namespace Ezdeliver\Tests\Factory;

use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Display\LinkedIssuePrDisplayStrategy;
use Ezdeliver\Display\MrLabelPrDisplayStrategy;
use Ezdeliver\Factory\PrDisplayStrategyFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class PrDisplayStrategyFactoryTest extends TestCase
{
    private function makeFactory(): PrDisplayStrategyFactory
    {
        return new PrDisplayStrategyFactory($this->createMock(SymfonyStyle::class));
    }

    public function testCreateReturnsLinkedIssueStrategyForLinkedIssueMode(): void
    {
        $strategy = $this->makeFactory()->create(PrSelectionMode::LinkedIssue);

        $this->assertInstanceOf(LinkedIssuePrDisplayStrategy::class, $strategy);
    }

    public function testCreateReturnsMrLabelStrategyForMrLabelMode(): void
    {
        $strategy = $this->makeFactory()->create(PrSelectionMode::MrLabel);

        $this->assertInstanceOf(MrLabelPrDisplayStrategy::class, $strategy);
    }
}
