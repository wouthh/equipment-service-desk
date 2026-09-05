<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\DatabaseTest;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthorizationTest extends DatabaseTest
{
    /** @return iterable<string,array{string,string,int}> */
    public static function roles(): iterable
    {
        $matrix = [
            'requester-a' => ['triage' => 403, 'assignment' => 403, 'resolution' => 403, 'cancellation' => 403],
            'requester-b' => ['triage' => 404, 'assignment' => 404, 'resolution' => 404, 'cancellation' => 404],
            'coordinator-a' => ['triage' => 409, 'assignment' => 200, 'resolution' => 403, 'cancellation' => 200],
            'technician-a' => ['triage' => 403, 'assignment' => 403, 'resolution' => 200, 'cancellation' => 403],
            'technician-b' => ['triage' => 404, 'assignment' => 404, 'resolution' => 404, 'cancellation' => 404],
        ];
        foreach ($matrix as $role => $actions) {
            foreach ($actions as $action => $status) {
                yield $role.'-'.$action => [$role, $action, $status];
            }
        }
    }

    #[DataProvider('roles')]
    public function testAssignedRoleActionMatrix(string $actor, string $action, int $status): void
    {
        $id = $this->insertRequest('assigned');
        $input = match ($action) {
            'triage' => ['impact' => 'degraded'],'assignment' => ['technicianId' => '10000000-0000-7000-8000-000000000006'],'resolution' => ['summary' => 'Synthetic resolution'],default => ['reason' => 'Synthetic cancellation']
        };
        $response = $this->call($actor, 'POST', '/api/v1/service-requests/'.$id.'/'.$action, $input, ['Idempotency-Key' => $this->newKey(), 'If-Match' => '"'.$id.':1"']);
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testListsHistoryAndReplayRecheckAccess(): void
    {
        $id = $this->insertRequest('assigned');
        $path = '/api/v1/service-requests/'.$id;
        self::assertSame([], $this->body($this->call('requester-b', 'GET', '/api/v1/service-requests'))['items']);
        self::assertSame([], $this->body($this->call('technician-b', 'GET', '/api/v1/service-requests'))['items']);
        self::assertSame(404, $this->call('requester-b', 'GET', $path.'/history')->getStatusCode());
        $key = $this->newKey();
        $payload = ['summary' => 'Synthetic finished'];
        $headers = ['Idempotency-Key' => $key, 'If-Match' => '"'.$id.':1"'];
        self::assertSame(200, $this->call('technician-a', 'POST', $path.'/resolution', $payload, $headers)->getStatusCode());
        $this->admin->executeStatement("UPDATE app_user SET active=false WHERE handle='technician-a'");
        self::assertSame(401, $this->call('technician-a', 'POST', $path.'/resolution', $payload, $headers)->getStatusCode());
    }

    public function testWithdrawPreconditionsAndConflict(): void
    {
        $id = $this->insertRequest('submitted');
        $path = '/api/v1/service-requests/'.$id.'/cancellation';
        $key = $this->newKey();
        $payload = ['reason' => 'Synthetic cancellation'];
        self::assertSame(428, $this->call('requester-a', 'POST', $path, $payload, ['Idempotency-Key' => $key])->getStatusCode());
        $headers = ['Idempotency-Key' => $key, 'If-Match' => '"'.$id.':1"'];
        self::assertSame(200, $this->call('requester-a', 'POST', $path, $payload, $headers)->getStatusCode());
        self::assertSame(200, $this->call('requester-a', 'POST', $path, $payload, $headers)->getStatusCode());
        self::assertSame(409, $this->call('requester-a', 'POST', $path, ['reason' => 'Changed'], $headers)->getStatusCode());
        self::assertSame(409, $this->call('coordinator-a', 'POST', $path, $payload, ['Idempotency-Key' => $this->newKey(), 'If-Match' => '"'.$id.':2"'])->getStatusCode());
    }

    public function testExpiredAndRevokedTokens(): void
    {
        $this->admin->executeStatement("UPDATE api_token SET expires_at=now()-interval '1 hour' WHERE principal_id=(SELECT id FROM app_user WHERE handle='requester-a')");
        self::assertSame(401, $this->call('requester-a','GET','/api/v1/me')->getStatusCode());
        $this->admin->executeStatement("UPDATE api_token SET revoked_at=now() WHERE principal_id=(SELECT id FROM app_user WHERE handle='requester-b')");
        self::assertSame(401,$this->call('requester-b','GET','/api/v1/me')->getStatusCode());
    }
}
