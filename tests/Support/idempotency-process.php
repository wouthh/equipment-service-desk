<?php

declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$kernel = new App\Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
if (!$container instanceof Psr\Container\ContainerInterface) {
    throw new LogicException();
}
$store = $container->get(App\Shared\Infrastructure\Store::class);
$idempotency = $container->get(App\Shared\Application\Idempotency::class);
if (!$store instanceof App\Shared\Infrastructure\Store || !$idempotency instanceof App\Shared\Application\Idempotency) {
    throw new LogicException();
}
$line = fgets(STDIN);
$data = json_decode(false === $line ? '' : $line, true, 8, JSON_THROW_ON_ERROR);
if (!is_array($data) || !is_string($data['key'] ?? null) || !is_bool($data['hold'] ?? null)) {
    throw new LogicException();
}
$actor = $store->em()->find(App\Access\Domain\Principal::class, '10000000-0000-7000-8000-000000000003');
if (!$actor instanceof App\Access\Domain\Principal) {
    throw new LogicException();
}
$pid = $store->em()->getConnection()->fetchOne('SELECT pg_backend_pid()');
if (!is_int($pid)) {
    throw new LogicException();
}
fwrite(STDOUT, 'READY:'.$pid."\n");
fflush(STDOUT);
if ("GO\n" !== fgets(STDIN)) {
    throw new LogicException();
}
$request = Symfony\Component\HttpFoundation\Request::create('/api/v1/equipment', 'POST');
$request->headers->set('Idempotency-Key', $data['key']);
try {
    $response = $idempotency->run($request, $actor, ['assetTag' => 'RACE-1'], function () use ($store, $data): Symfony\Component\HttpFoundation\JsonResponse {
        if ($data['hold']) {
            fwrite(STDOUT, "INSIDE\n");
            fflush(STDOUT);
            if ("COMMIT\n" !== fgets(STDIN)) {
                throw new LogicException();
            }
        }
        $store->em()->persist(new App\Equipment\Domain\Equipment('20000000-0000-7000-8000-000000000099', 'RACE-1', 'Synthetic concurrent command', App\Equipment\Domain\Criticality::Normal, new DateTimeImmutable('2026-01-01T00:00:00Z')));

        return new Symfony\Component\HttpFoundation\JsonResponse(['result' => 'synthetic'], 201);
    });
    fwrite(STDOUT, $response->getStatusCode().':'.($response->headers->get('Idempotency-Replayed') ?? 'false')."\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error::class."\n");
    exit(1);
}
$kernel->shutdown();
