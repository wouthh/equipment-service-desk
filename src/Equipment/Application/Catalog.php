<?php

declare(strict_types=1);

namespace App\Equipment\Application;

use App\Equipment\Domain\Equipment;
use App\Shared\Http\Problem;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Uid\Uuid;

final readonly class Catalog
{
    public function __construct(private Store $store)
    {
    }

    public function get(string $id): Equipment
    {
        if (!Uuid::isValid($id)) {
            throw new Problem(404, 'not_found');
        }

        return $this->store->em()->find(Equipment::class, $id) ?? throw new Problem(404, 'not_found');
    }

    /** @return list<array{id:string,assetTag:string,name:string,criticality:string,registeredAt:string}> */
    public function page(int $page, int $limit): array
    {
        return array_map(static fn (Equipment $e): array => $e->view(), $this->store->em()->getRepository(Equipment::class)->findBy([], ['assetTag' => 'ASC'], $limit, ($page - 1) * $limit));
    }
}
