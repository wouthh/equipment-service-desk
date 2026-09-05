<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Reporting\Application\GenerateReportHandler;
use App\Reporting\Message\GenerateReport;
use App\Tests\Support\DatabaseTest;

final class ReportingTest extends DatabaseTest
{
    public function testReportLimitAllowsReplayButRejectsSixthNewRequest(): void
    {
        $body = ['from' => '2026-01-01T00:00:00Z', 'to' => '2026-01-02T00:00:00Z'];
        for ($i = 0; $i < 5; ++$i) {
            $key = $this->newKey();
            $response = $this->call('coordinator-a', 'POST', '/api/v1/reports', $body, ['Idempotency-Key' => $key]);
            self::assertSame(202, $response->getStatusCode());
            self::assertSame(202, $this->call('coordinator-a', 'POST', '/api/v1/reports', $body, ['Idempotency-Key' => $key])->getStatusCode());
            $job = $this->body($response);
            self::assertIsString($job['id']);
            $handler = self::getContainer()->get(GenerateReportHandler::class);
            self::assertInstanceOf(GenerateReportHandler::class, $handler);
            $handler(new GenerateReport($job['id']));
        }
        self::assertSame(429, $this->call('coordinator-a', 'POST', '/api/v1/reports', $body, ['Idempotency-Key' => $this->newKey()])->getStatusCode());
        self::assertSame(5, $this->recordCount('report_job'));
        self::assertSame(5, $this->recordCount('idempotency_record'));
    }

    public function testReportIntervalIncludesFromAndExcludesTo(): void
    {
        $included = $this->insertRequest('submitted');
        $excluded = $this->insertRequest('submitted');
        $this->admin->executeStatement('UPDATE service_request SET submitted_at=? WHERE id=?', ['2026-01-02T09:00:00Z', $excluded]);
        $response = $this->call('coordinator-a', 'POST', '/api/v1/reports', ['from' => '2026-01-01T09:00:00Z', 'to' => '2026-01-02T09:00:00Z'], ['Idempotency-Key' => $this->newKey()]);
        self::assertSame(202, $response->getStatusCode());
        $job = $this->body($response);
        self::assertIsString($job['id']);
        $handler = self::getContainer()->get(GenerateReportHandler::class);
        self::assertInstanceOf(GenerateReportHandler::class, $handler);
        $handler(new GenerateReport($job['id']));
        $csv = (string) $this->call('coordinator-a', 'GET', '/api/v1/reports/'.$job['id'].'/download')->getContent();
        self::assertStringContainsString($included, $csv);
        self::assertStringNotContainsString($excluded, $csv);
    }

    public function testReportAuthorizationAndImmutableRedelivery(): void
    {
        $this->insertRequest('resolved');
        $body = ['from' => '2026-01-01T00:00:00Z', 'to' => '2026-01-03T00:00:00Z'];
        self::assertSame(403, $this->call('requester-a', 'POST', '/api/v1/reports', $body, ['Idempotency-Key' => $this->newKey()])->getStatusCode());
        $key = $this->newKey();
        $response = $this->call('coordinator-a', 'POST', '/api/v1/reports', $body, ['Idempotency-Key' => $key]);
        self::assertSame(202, $response->getStatusCode(), (string) $response->getContent());
        $job = $this->body($response);
        self::assertIsString($job['id']);
        $path = '/api/v1/reports/'.$job['id'];
        self::assertSame(1, $this->recordCount('messenger_messages'));
        self::assertSame(202, $this->call('coordinator-a', 'POST', '/api/v1/reports', $body, ['Idempotency-Key' => $key])->getStatusCode());
        self::assertSame(409, $this->call('coordinator-a', 'POST', '/api/v1/reports', $body, ['Idempotency-Key' => $this->newKey()])->getStatusCode());
        self::assertSame(409, $this->call('coordinator-a', 'GET', $path.'/download')->getStatusCode());
        $handler = self::getContainer()->get(GenerateReportHandler::class);
        self::assertInstanceOf(GenerateReportHandler::class, $handler);
        $handler(new GenerateReport($job['id']));
        $download = $this->call('coordinator-a', 'GET', $path.'/download');
        self::assertSame(200, $download->getStatusCode(), (string) $download->getContent());
        self::assertStringNotContainsString('Synthetic description', (string) $download->getContent());
        self::assertStringNotContainsString('Synthetic resolution', (string) $download->getContent());
        self::assertStringContainsString('172800', (string) $download->getContent());
        self::assertSame(403, $this->call('technician-a', 'GET', $path.'/download')->getStatusCode());
        self::assertSame(403, $this->call('requester-a', 'GET', $path)->getStatusCode());
        $handler = self::getContainer()->get(GenerateReportHandler::class);
        self::assertInstanceOf(GenerateReportHandler::class, $handler);
        $handler(new GenerateReport($job['id']));
        self::assertSame($download->getContent(), $this->call('coordinator-b', 'GET', $path.'/download')->getContent());
        self::assertSame(2, $this->recordCount('audit_entry'));
    }
}
