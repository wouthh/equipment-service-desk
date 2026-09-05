<?php

declare(strict_types=1);
// Synthetic subprocess protocol. No tokens, provider data or command-line secrets.
require __DIR__.'/../bootstrap.php';
$kernel = new App\Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer();
$testContainer = $container->get('test.service_container');
if (!$testContainer instanceof Psr\Container\ContainerInterface) {
    throw new LogicException();
}
$store = $testContainer->get(App\Shared\Infrastructure\Store::class);
if (!$store instanceof App\Shared\Infrastructure\Store) {
    throw new LogicException();
}
$line = fgets(STDIN);
$data = json_decode(false === $line ? '' : $line, true, 16, JSON_THROW_ON_ERROR);
if (!is_array($data) || !is_string($data['id'] ?? null) || !is_string($data['technician'] ?? null)) {
    throw new LogicException();
}
$em = $store->em();
$item = $em->find(App\ServiceRequests\Domain\ServiceRequest::class, $data['id']);
$actor = $em->find(App\Access\Domain\Principal::class, '10000000-0000-7000-8000-000000000003');
if (!$item instanceof App\ServiceRequests\Domain\ServiceRequest || !$actor instanceof App\Access\Domain\Principal) {
    throw new LogicException();
}
$handler = $testContainer->get(App\ServiceRequests\Application\TransitionRequest::class);
if (!$handler instanceof App\ServiceRequests\Application\TransitionRequest) {
    throw new LogicException();
}
$etag = $item->etag();
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
if ("GO\n" !== fgets(STDIN)) {
    throw new LogicException();
}
try {
    $em->getConnection()->transactional(function () use ($handler, $actor, $item, $data, $etag): void {
        $handler->execute($actor, $item, 'assignment', new App\ServiceRequests\Application\Input\AssignmentInput($data['technician']), $etag, Symfony\Component\Uid\Uuid::v7()->toRfc4122());
    });
    fwrite(STDOUT, "200\n");
} catch (Doctrine\ORM\OptimisticLockException) {
    fwrite(STDOUT, "412\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error::class."\n");
    exit(1);
}
$kernel->shutdown();
