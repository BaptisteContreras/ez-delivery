<?php

namespace Ezdeliver\Tests\Factory;

use Ezdeliver\Config\Model\PrSelectionMode;
use Ezdeliver\Core\Vcs\IssueSelectorReleaseInfoFormatter;
use Ezdeliver\Core\Vcs\MrLabelReleaseInfoFormatter;
use Ezdeliver\Factory\PrReleaseInfoFormatterFactory;
use PHPUnit\Framework\TestCase;

class PrReleaseInfoFormatterFactoryTest extends TestCase
{
    public function testCreateReturnsIssueSelectorFormatterForLinkedIssueMode(): void
    {
        $formatter = (new PrReleaseInfoFormatterFactory())->create(PrSelectionMode::LinkedIssue);

        $this->assertInstanceOf(IssueSelectorReleaseInfoFormatter::class, $formatter);
    }

    public function testCreateReturnsMrLabelFormatterForMrLabelMode(): void
    {
        $formatter = (new PrReleaseInfoFormatterFactory())->create(PrSelectionMode::MrLabel);

        $this->assertInstanceOf(MrLabelReleaseInfoFormatter::class, $formatter);
    }
}
