<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\DatabaseTest;
use PHPUnit\Framework\Attributes\DataProvider;

final class InputAndReplayTest extends DatabaseTest
{
    /** @return iterable<array{array<string,mixed>}> */
    public static function invalidSubmissions(): iterable
    {
        foreach (['ownerId', 'priority', 'state', 'submittedAt', 'technicianId'] as $field) {
            yield [[$field => 'not-assignable']];
        }
        yield [['title' => '   ']];
        yield [['title' => str_repeat('x', 121)]];
        yield [['description' => str_repeat('x', 4001)]];
        yield [['reportedImpact' => 'critical']];
        yield [['equipmentId' => 'not-a-uuid']];
        yield [['title' => 12]];
    }

    /** @param array<string,mixed> $override */
    #[DataProvider('invalidSubmissions')]
    public function testInvalidSubmissionLeavesNoEffects(array $override): void
    {
        $body = ['equipmentId' => '20000000-0000-7000-8000-000000000001', 'title' => 'Synthetic', 'description' => 'Synthetic input', 'reportedImpact' => 'stopped'];
        self::assertSame(422, $this->call('requester-a', 'POST', '/api/v1/service-requests', array_replace($body, $override), ['Idempotency-Key' => $this->newKey()])->getStatusCode());
        self::assertSame(0, $this->recordCount('service_request'));
        self::assertSame(0, $this->recordCount('audit_entry'));
        self::assertSame(0, $this->recordCount('idempotency_record'));
    }

    public function testKeyReuseConflictsButAnotherPrincipalHasAnIndependentNamespace(): void
    {
        $key = $this->newKey();
        $body = ['equipmentId' => '20000000-0000-7000-8000-000000000001', 'title' => 'Synthetic', 'description' => 'Synthetic input', 'reportedImpact' => 'stopped'];
        self::assertSame(201, $this->call('requester-a', 'POST', '/api/v1/service-requests', $body, ['Idempotency-Key' => $key])->getStatusCode());
        self::assertSame(409, $this->call('requester-a', 'POST', '/api/v1/service-requests', array_replace($body, ['title' => 'Different input']), ['Idempotency-Key' => $key])->getStatusCode());
        self::assertSame(201, $this->call('requester-b', 'POST', '/api/v1/service-requests', $body, ['Idempotency-Key' => $key])->getStatusCode());
        self::assertSame(2, $this->recordCount('service_request'));
        self::assertSame(2, $this->recordCount('audit_entry'));
    }

    public function testPreconditionsAndInvalidKeysAreRejectedBeforeMutation(): void
    {
        $id = $this->insertRequest('submitted');
        $path = '/api/v1/service-requests/'.$id.'/triage';
        self::assertSame(428, $this->call('coordinator-a', 'POST', $path, ['impact' => 'stopped'], ['Idempotency-Key' => $this->newKey()])->getStatusCode());
        self::assertSame(400, $this->call('coordinator-a', 'POST', $path, ['impact' => 'stopped'], ['Idempotency-Key' => $this->newKey(), 'If-Match' => '*'])->getStatusCode());
        self::assertSame(400, $this->call('coordinator-a', 'POST', $path, ['impact' => 'stopped'], ['Idempotency-Key' => str_repeat('x', 100), 'If-Match' => '"'.$id.':1"'])->getStatusCode());
        self::assertSame('submitted', $this->admin->fetchOne('SELECT state FROM service_request WHERE id=?', [$id]));
        self::assertSame(0, $this->recordCount('audit_entry'));
        self::assertSame(0, $this->recordCount('idempotency_record'));
    }
}
