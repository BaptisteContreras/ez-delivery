<?php

namespace Ezdeliver\Tests\Repo;

use Ezdeliver\Core\Model\Pr;
use Ezdeliver\Core\Model\Selector;
use Ezdeliver\Core\Repo\PrMergeOrderSorter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class PrMergeOrderSorterTest extends TestCase
{
    private const string PREFIX = 'merge-order::';

    private function makePr(int $iid, array $labels, ?int $selectorId = null): Pr
    {
        return new Pr($iid, "PR $iid", new Selector($selectorId ?? $iid, "Selector $iid", $labels), []);
    }

    /**
     * @param array<Pr> $prs
     *
     * @return array<int>
     */
    private function ids(array $prs): array
    {
        return array_map(fn (Pr $pr) => $pr->getId(), $prs);
    }

    private function sorter(?SymfonyStyle $io = null): PrMergeOrderSorter
    {
        return new PrMergeOrderSorter($io ?? $this->createMock(SymfonyStyle::class));
    }

    public function testLabelledFirstSortedByOrderThenUnlabelledByIid(): void
    {
        $result = $this->sorter()->sort([
            $this->makePr(5, []),
            $this->makePr(3, ['merge-order::2']),
            $this->makePr(4, []),
            $this->makePr(9, ['merge-order::1']),
        ], self::PREFIX);

        $this->assertSame([9, 3, 4, 5], $this->ids($result));
        $this->assertSame([1, 2, null, null], array_map(fn (Pr $pr) => $pr->getMergeOrder(), $result));
    }

    public function testTiesBrokenByIid(): void
    {
        $result = $this->sorter()->sort([
            $this->makePr(7, ['merge-order::1']),
            $this->makePr(2, ['merge-order::1']),
        ], self::PREFIX);

        $this->assertSame([2, 7], $this->ids($result));
    }

    public function testTieBreakUsesMrIidNotSelectorId(): void
    {
        $result = $this->sorter()->sort([
            $this->makePr(7, ['merge-order::1'], 10),
            $this->makePr(2, ['merge-order::1'], 90),
        ], self::PREFIX);

        $this->assertSame([2, 7], $this->ids($result));
    }

    public function testLeadingZerosAccepted(): void
    {
        $result = $this->sorter()->sort([$this->makePr(1, ['merge-order::007'])], self::PREFIX);

        $this->assertSame(7, $result[0]->getMergeOrder());
    }

    public function testZeroIsValid(): void
    {
        $result = $this->sorter()->sort([
            $this->makePr(1, []),
            $this->makePr(2, ['merge-order::0']),
        ], self::PREFIX);

        $this->assertSame([2, 1], $this->ids($result));
        $this->assertSame(0, $result[0]->getMergeOrder());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'letters' => ['abc'];
        yield 'negative' => ['-1'];
        yield 'empty' => [''];
        yield 'decimal' => ['1.5'];
        yield 'too long' => ['12345678901'];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesWarnAndAreUnlabelled(string $value): void
    {
        $label = 'merge-order::'.$value;
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('warning')
            ->with($this->logicalAnd($this->stringContains('#2'), $this->stringContains($label)));

        $result = $this->sorter($io)->sort([
            $this->makePr(2, [$label]),
            $this->makePr(1, []),
        ], self::PREFIX);

        $this->assertSame([1, 2], $this->ids($result));
        $this->assertNull($result[1]->getMergeOrder());
    }

    public function testMultipleOrderLabelsUseLowestAndWarn(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning')->with($this->stringContains('#1'));

        $result = $this->sorter($io)->sort([$this->makePr(1, ['merge-order::5', 'merge-order::2'])], self::PREFIX);

        $this->assertSame(2, $result[0]->getMergeOrder());
    }

    public function testNonMatchingLabelsIgnoredSilently(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('warning');

        $result = $this->sorter($io)->sort([$this->makePr(1, ['Merge-Order::1', 'merge-order:1', 'to-deliver'])], self::PREFIX);

        $this->assertNull($result[0]->getMergeOrder());
    }

    public function testCustomPrefix(): void
    {
        $result = $this->sorter()->sort([
            $this->makePr(1, []),
            $this->makePr(2, ['prio/1']),
        ], 'prio/');

        $this->assertSame([2, 1], $this->ids($result));
    }

    public function testReturnsList(): void
    {
        $result = $this->sorter()->sort([3 => $this->makePr(1, []), 8 => $this->makePr(2, [])], self::PREFIX);

        $this->assertTrue(array_is_list($result));
    }

    public function testEmptyPrefixDisablesOrderingWithSingleWarning(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning')->with($this->stringContains('prefix is empty'));

        $result = $this->sorter($io)->sort([
            $this->makePr(2, ['1', 'to-deliver']),
            $this->makePr(1, ['5']),
        ], '');

        $this->assertSame([1, 2], $this->ids($result));
        $this->assertSame([null, null], array_map(fn (Pr $pr) => $pr->getMergeOrder(), $result));
    }
}
