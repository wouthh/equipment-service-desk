<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

final readonly class Store
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function em(): EntityManagerInterface
    {
        $manager = $this->registry->getManager();
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException('ORM manager required.');
        }
        if (!$manager->isOpen()) {
            $manager = $this->registry->resetManager();
            if (!$manager instanceof EntityManagerInterface) {
                throw new \LogicException('ORM manager required.');
            }
        }

        return $manager;
    }
}
