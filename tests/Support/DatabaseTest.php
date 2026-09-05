<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Access\Domain\AccessToken;
use App\Access\Domain\Principal;
use App\Access\Infrastructure\DemoSeed;
use App\Shared\Infrastructure\Store;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

abstract class DatabaseTest extends WebTestCase
{
    protected KernelBrowser $browser;
    protected Connection $admin;
    /** @var array<string,string> */
    private array $tokens = [];

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->browser = self::createClient();
        $url = getenv('TEST_MIGRATION_URL');
        if (!is_string($url) || !str_ends_with($url, '/desk_test')) {
            throw new \RuntimeException('Disposable test database required.');
        }
        $params = (new \Doctrine\DBAL\Tools\DsnParser(['postgresql' => 'pdo_pgsql']))->parse($url);
        $this->admin = DriverManager::getConnection($params);
        $this->admin->executeStatement('TRUNCATE audit_entry, idempotency_record, api_token, service_request, equipment, app_user CASCADE');
        if ($this->admin->createSchemaManager()->tablesExist(['report_job'])) {
            $this->admin->executeStatement('TRUNCATE report_job, messenger_messages');
        }
        $seed = self::getContainer()->get(DemoSeed::class);
        if (!$seed instanceof DemoSeed) {
            throw new \LogicException();
        }
        $seed->seed();
        $store = self::getContainer()->get(Store::class);
        if (!$store instanceof Store) {
            throw new \LogicException();
        }
        foreach ($store->em()->getRepository(Principal::class)->findAll() as $actor) {
            $raw = bin2hex(random_bytes(32));
            $this->tokens[$actor->handle()] = $raw;
            $store->em()->persist(new AccessToken(Uuid::v7()->toRfc4122(), hash('sha256', $raw), $actor, new \DateTimeImmutable('+1 hour')));
        }
        $store->em()->flush();
        $store->em()->clear();
        $this->tokens['none'] = 'invalid';
        $cache = self::getContainer()->get('cache.rate_limiter');
        if ($cache instanceof \Psr\Cache\CacheItemPoolInterface) {
            $cache->clear();
        }
    }

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string>     $headers
     */
    protected function call(string $actor, string $method, string $path, ?array $body = null, array $headers = []): Response
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->tokens[$actor], 'CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }
        $this->browser->request($method, $path, [], [], $server, null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));
        $response = $this->browser->getResponse();
        $validator = (new ValidatorBuilder())->fromYamlFile(dirname(__DIR__, 2).'/openapi/openapi.yaml');
        $factory = new Psr17Factory();
        $bridge = new PsrHttpFactory($factory, $factory, $factory, $factory);
        $actual = $this->browser->getRequest();
        if ($response->getStatusCode() < 400) {
            $address = $validator->getRequestValidator()->validate($bridge->createRequest($actual));
        } else {
            $template = preg_replace('~(/api/v1/(?:equipment|service-requests|reports))/[^/]+~', '$1/{id}', $actual->getPathInfo());
            if (!is_string($template)) {
                throw new \LogicException();
            }
            $address = new OperationAddress($template, strtolower($method));
        }
        $validator->getResponseValidator()->validate($address, $bridge->createResponse($response));

        return $response;
    }

    /** @return array<string,mixed> */
    protected function body(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $result = [];
        foreach ($data as $key => $value) {
            self::assertIsString($key);
            $result[$key] = $value;
        }

        return $result;
    }

    protected function recordCount(string $table): int
    {
        if (!in_array($table, ['audit_entry', 'idempotency_record', 'service_request', 'report_job', 'messenger_messages'], true)) {
            throw new \InvalidArgumentException();
        }
        $value = $this->admin->fetchOne('SELECT count(*) FROM '.$table);
        self::assertIsInt($value);

        return $value;
    }

    protected function insertRequest(string $state): string
    {
        if (!in_array($state, ['submitted', 'triaged', 'assigned', 'resolved', 'cancelled'], true)) {
            throw new \InvalidArgumentException();
        }
        $id = $this->newKey();
        $triage = 'submitted' === $state ? null : json_encode(['policyVersion' => 'triage-v1', 'criticality' => 'normal', 'impact' => 'stopped', 'priority' => 'medium', 'targetSeconds' => 86400, 'dueAt' => '2026-01-02T09:00:00+00:00'], JSON_THROW_ON_ERROR);
        $this->admin->insert('service_request', [
            'id' => $id, 'equipment_id' => '20000000-0000-7000-8000-000000000001', 'requester_id' => '10000000-0000-7000-8000-000000000001',
            'state' => $state, 'version' => 1, 'title' => 'Synthetic service', 'description' => 'Synthetic description', 'reported_impact' => 'stopped', 'submitted_at' => '2026-01-01T09:00:00Z', 'triage' => $triage,
            'technician_id' => in_array($state, ['assigned', 'resolved'], true) ? '10000000-0000-7000-8000-000000000005' : null,
            'resolution' => 'resolved' === $state ? 'Synthetic resolution' : null, 'resolved_at' => 'resolved' === $state ? '2026-01-03T09:00:00Z' : null,
            'cancellation_reason' => 'cancelled' === $state ? 'Synthetic cancellation' : null, 'cancelled_at' => 'cancelled' === $state ? '2026-01-01T10:00:00Z' : null,
        ]);

        return $id;
    }

    protected function newKey(): string
    {
        return Uuid::v7()->toRfc4122();
    }

    protected function tearDown(): void
    {
        $this->admin->close();
        parent::tearDown();
    }
}
