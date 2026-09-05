<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\Principal;
use App\Access\Domain\Role;
use App\Shared\Http\Problem;
use App\Shared\Infrastructure\Store;

final readonly class Directory
{
    public function __construct(private Store $store)
    {
    }

    public function technician(string $id): Principal
    {
        $actor = $this->store->em()->find(Principal::class, $id);
        if (null === $actor || !$actor->active() || Role::Technician !== $actor->role()) {
            throw new Problem(422, 'invalid_technician');
        }

        return $actor;
    }

    /** @return list<array{id:string,handle:string,label:string,role:string}> */
    public function technicians(): array
    {
        return array_map(static fn (Principal $p): array => $p->view(), $this->store->em()->getRepository(Principal::class)->findBy(['role' => Role::Technician, 'active' => true], ['handle' => 'ASC'], 100));
    }
}
