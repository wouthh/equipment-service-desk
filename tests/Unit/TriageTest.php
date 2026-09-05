<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Equipment\Domain\Criticality;
use App\ServiceRequests\Application\TriageV1;
use App\ServiceRequests\Domain\Impact;
use App\ServiceRequests\Domain\Priority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TriageTest extends TestCase
{
    /** @return iterable<string,array{Criticality,Impact,Priority,int}> */
    public static function cases(): iterable
    {
        yield 'critical stopped' => [Criticality::Critical, Impact::Stopped, Priority::High, 4];
        yield 'normal stopped' => [Criticality::Normal, Impact::Stopped, Priority::Medium, 24];
        yield 'critical degraded' => [Criticality::Critical, Impact::Degraded, Priority::Medium, 24];
        yield 'normal degraded' => [Criticality::Normal, Impact::Degraded, Priority::Low, 72];
    }

    #[DataProvider('cases')]
    public function testDecision(Criticality $criticality, Impact $impact, Priority $priority, int $hours): void
    {
        $submitted = new \DateTimeImmutable('2026-03-29T00:30:00+01:00');
        $decision = (new TriageV1())->decide($criticality, $impact, $submitted);
        self::assertSame('triage-v1', $decision->policyVersion);
        self::assertSame($priority, $decision->priority);
        self::assertSame($hours * 3600, $decision->dueAt->getTimestamp() - $submitted->getTimestamp());
        self::assertSame($hours * 3600, $decision->targetSeconds);
    }
}
