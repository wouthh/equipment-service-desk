<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Reporting\Domain\ReportCriteria;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportCriteriaTest extends TestCase
{
    /** @return iterable<array{string,string}> */
    public static function invalid(): iterable
    {
        yield ['yesterday', 'tomorrow'];
        yield ['2026-02-30T00:00:00Z', '2026-03-03T00:00:00Z'];
        yield ['2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z'];
        yield ['2026-02-01T00:00:00Z', '2026-01-01T00:00:00Z'];
        yield ['2026-01-01T00:00:00Z', '2026-02-01T00:00:01Z'];
        yield ['2026-01-01', '2026-01-02'];
    }

    #[DataProvider('invalid')]
    public function testRejectsInvalidIntervals(string $from, string $to): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReportCriteria::parse($from, $to);
    }

    public function testAcceptsExactly31DaysAndNormalizesOffsets(): void
    {
        $c = ReportCriteria::parse('2026-01-01T01:00:00+01:00', '2026-02-01T00:00:00Z');
        self::assertSame('2026-01-01T00:00:00+00:00', $c->from->format(DATE_ATOM));
        self::assertSame(31 * 86400, $c->to->getTimestamp() - $c->from->getTimestamp());
    }
}
