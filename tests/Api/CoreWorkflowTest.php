<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\DatabaseTest;

final class CoreWorkflowTest extends DatabaseTest
{
    public function testCompleteWorkflowAndReplay(): void
    {
        $key = $this->newKey();
        $input = ['equipmentId' => '20000000-0000-7000-8000-000000000002', 'title' => 'Synthetic service', 'description' => 'Synthetic stopped equipment', 'reportedImpact' => 'stopped'];
        $created = $this->call('requester-a', 'POST', '/api/v1/service-requests', $input, ['Idempotency-Key' => $key]);
        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        $body = $this->body($created);
        self::assertIsString($body['id']);
        $path = '/api/v1/service-requests/'.$body['id'];
        $replay = $this->call('requester-a', 'POST', '/api/v1/service-requests', $input, ['Idempotency-Key' => $key]);
        self::assertSame($created->getContent(), $replay->getContent());
        self::assertSame('true', $replay->headers->get('Idempotency-Replayed'));
        self::assertSame(404, $this->call('requester-b', 'GET', $path)->getStatusCode());
        self::assertSame(404, $this->call('technician-a', 'GET', $path)->getStatusCode());
        $triage = $this->call('coordinator-a', 'POST', $path.'/triage', ['impact' => 'stopped'], ['Idempotency-Key' => $this->newKey(), 'If-Match' => (string) $created->headers->get('ETag')]);
        self::assertSame(200, $triage->getStatusCode(), (string) $triage->getContent());
        $assignKey = $this->newKey();
        $assignInput = ['technicianId' => '10000000-0000-7000-8000-000000000005'];
        $assignHeaders = ['Idempotency-Key' => $assignKey, 'If-Match' => (string) $triage->headers->get('ETag')];
        $assigned = $this->call('coordinator-a', 'POST', $path.'/assignment', $assignInput, $assignHeaders);
        self::assertSame(200, $assigned->getStatusCode(), (string) $assigned->getContent());
        self::assertSame(412, $this->call('coordinator-b', 'POST', $path.'/assignment', $assignInput, ['Idempotency-Key' => $this->newKey(), 'If-Match' => (string) $triage->headers->get('ETag')])->getStatusCode());
        self::assertSame($assigned->getContent(), $this->call('coordinator-a', 'POST', $path.'/assignment', $assignInput, $assignHeaders)->getContent());
        self::assertSame(403, $this->call('coordinator-a', 'POST', $path.'/resolution', ['summary' => 'Synthetic fixed'], ['Idempotency-Key' => $this->newKey(), 'If-Match' => (string) $assigned->headers->get('ETag')])->getStatusCode());
        self::assertSame(404, $this->call('technician-b', 'GET', $path.'/history')->getStatusCode());
        $resolved = $this->call('technician-a', 'POST', $path.'/resolution', ['summary' => 'Synthetic fixed'], ['Idempotency-Key' => $this->newKey(), 'If-Match' => (string) $assigned->headers->get('ETag')]);
        self::assertSame(200, $resolved->getStatusCode(), (string) $resolved->getContent());
        self::assertSame('resolved', $this->body($resolved)['state']);
        self::assertSame(200, $this->call('technician-a', 'GET', $path)->getStatusCode());
        self::assertSame(4, $this->recordCount('audit_entry'));
        self::assertSame(4, $this->recordCount('idempotency_record'));
    }

    public function testAuthenticationAndInputFailClosed(): void
    {
        self::assertSame(401, $this->call('none', 'GET', '/api/v1/me')->getStatusCode());
        self::assertSame(403, $this->call('requester-a', 'POST', '/api/v1/equipment', ['assetTag' => 'DEMO-3', 'name' => 'Synthetic', 'criticality' => 'normal'], ['Idempotency-Key' => $this->newKey()])->getStatusCode());
        self::assertSame(422, $this->call('requester-a', 'POST', '/api/v1/service-requests', ['role' => 'coordinator'], ['Idempotency-Key' => $this->newKey()])->getStatusCode());
        self::assertSame(404, $this->call('requester-a', 'GET', '/api/v1/service-requests/not-a-uuid')->getStatusCode());
    }
}
