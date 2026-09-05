<?php

declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
$kernel = new App\Kernel('test', true);
$kernel->boot();

$registry = $kernel->getContainer()->get('doctrine');
if (!$registry instanceof Doctrine\Persistence\ManagerRegistry) {
    throw new LogicException();
}

return $registry->getManager();
