<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Reporting\Application\CsvReport;
use App\Reporting\Application\ReportLimit;
use App\Reporting\Infrastructure\ReportSerializer;
use App\Reporting\Message\GenerateReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class ReportSafetyTest extends TestCase
{
    /** @return iterable<array{string}> */
    public static function formulas(): iterable
    {
        foreach (['=1+1', '+1', '-1', '@SUM(A1)', '  =CMD()', "\ttext", "\ntext"] as $value) {
            yield [$value];
        }
    }

    #[DataProvider('formulas')]
    public function testFormulaNeutralization(string $value): void
    {
        self::assertSame("'".$value, CsvReport::safeCell($value));
    }

    public function testRowLimit(): void
    {
        $row = ['id' => 'synthetic', 'asset_tag' => 'ASSET-1', 'state' => 'submitted', 'submitted_at' => '2026-01-01T00:00:00Z'];
        $this->expectException(ReportLimit::class);
        (new CsvReport())->generate(array_fill(0, 10001, $row), new \DateTimeImmutable('2026-01-02T00:00:00Z'));
    }

    public function testByteLimit(): void
    {
        $row = ['id' => str_repeat('x', 5242881), 'asset_tag' => 'ASSET-1', 'state' => 'submitted', 'submitted_at' => '2026-01-01T00:00:00Z'];
        $this->expectException(ReportLimit::class);
        (new CsvReport())->generate([$row], new \DateTimeImmutable('2026-01-02T00:00:00Z'));
    }

    public function testJsonRoundTripKeepsBoundedRetryCount(): void
    {
        $serializer = new ReportSerializer();
        $encoded = $serializer->encode(new Envelope(new GenerateReport('30000000-0000-7000-8000-000000000001'), [new RedeliveryStamp(3)]));
        $decoded = $serializer->decode($encoded);
        self::assertInstanceOf(GenerateReport::class, $decoded->getMessage());
        self::assertSame(3, $decoded->last(RedeliveryStamp::class)?->getRetryCount());
        self::assertStringNotContainsString('O:', $encoded['body']);
    }

    /** @return iterable<array{string}> */
    public static function invalidMessages(): iterable
    {
        foreach (['O:8:"stdClass":0:{}', '{}', '{"type":"unknown","version":1,"reportId":"30000000-0000-7000-8000-000000000001"}', '{"type":"generate_report","version":2,"reportId":"30000000-0000-7000-8000-000000000001"}'] as $body) {
            yield [$body];
        }
    }

    #[DataProvider('invalidMessages')]
    public function testMessageRejection(string $body): void
    {
        $this->expectException(MessageDecodingFailedException::class);
        (new ReportSerializer())->decode(['body' => $body, 'headers' => []]);
    }
}
