<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Access\Domain\Principal;
use App\Shared\Http\Problem;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Rows;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class Idempotency
{
    public function __construct(private Store $store, private Ids $ids, private ClockInterface $clock)
    {
    }

    /**
     * @param array<string,mixed>     $payload
     * @param callable():JsonResponse $operation
     */
    public function run(Request $http, Principal $actor, array $payload, callable $operation): JsonResponse
    {
        $key = $http->headers->get('Idempotency-Key', '');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $key)) {
            throw new Problem(400, 'idempotency_key_required');
        }
        ksort($payload);
        $fingerprint = hash('sha256', json_encode([$http->getMethod(), $http->getPathInfo(), $payload, $http->headers->get('If-Match')], JSON_THROW_ON_ERROR));
        $digest = hash('sha256', strtolower($key));
        $em = $this->store->em();
        $db = $em->getConnection();

        return $db->transactional(function () use ($db, $em, $actor, $digest, $fingerprint, $operation): JsonResponse {
            $db->executeStatement("SET LOCAL lock_timeout = '2s'");
            $db->executeStatement('INSERT INTO idempotency_record (id,principal_id,key_digest,fingerprint,created_at) VALUES (?,?,?,?,?) ON CONFLICT (principal_id,key_digest) DO NOTHING',
                [$this->ids->next(), $actor->id(), $digest, $fingerprint, $this->clock->now()->format('Y-m-d H:i:sP')]);
            $row = $db->fetchAssociative('SELECT fingerprint,response FROM idempotency_record WHERE principal_id=? AND key_digest=? FOR UPDATE', [$actor->id(), $digest]);
            if (false === $row) {
                throw new \LogicException('Reservation missing.');
            }
            if (!hash_equals(Rows::text($row, 'fingerprint'), $fingerprint)) {
                throw new Problem(409, 'idempotency_conflict');
            }
            if (null !== $row['response']) {
                $stored = json_decode(Rows::text($row, 'response'), true, 16, JSON_THROW_ON_ERROR);
                if (!is_array($stored) || !is_int($stored['status'] ?? null) || !is_array($stored['headers'] ?? null) || !is_string($stored['body'] ?? null)) {
                    throw new \LogicException('Invalid stored response.');
                }
                $headers = [];
                foreach ($stored['headers'] as $name => $value) {
                    if (is_string($name) && is_string($value) && in_array($name, ['ETag', 'Location'], true)) {
                        $headers[$name] = $value;
                    }
                }
                $headers['Idempotency-Replayed'] = 'true';

                return JsonResponse::fromJsonString($stored['body'], $stored['status'], $headers);
            }
            $result = $operation();
            $em->flush();
            $body = $result->getContent();
            if (false === $body) {
                throw new \LogicException('Missing response.');
            }
            $headers = [];
            foreach (['ETag', 'Location'] as $name) {
                if (null !== ($value = $result->headers->get($name))) {
                    $headers[$name] = $value;
                }
            }
            $db->executeStatement('UPDATE idempotency_record SET response=? WHERE principal_id=? AND key_digest=?',
                [json_encode(['status' => $result->getStatusCode(), 'body' => $body, 'headers' => $headers], JSON_THROW_ON_ERROR), $actor->id(), $digest]);

            return $result;
        });
    }
}
