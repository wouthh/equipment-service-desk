<?php

declare(strict_types=1);

namespace App\ServiceRequests\Application;

use App\Access\Application\Permissions;
use App\Access\Domain\Principal;
use App\Access\Domain\Role;
use App\Audit\Domain\AuditEntry;
use App\ServiceRequests\Domain\ServiceRequest;
use App\Shared\Http\Problem;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Uid\Uuid;

final readonly class RequestQueries
{
    public function __construct(private Store $store, private Permissions $permissions)
    {
    }

    public function visible(string $id, Principal $actor): ServiceRequest
    {
        if (!Uuid::isValid($id)) {
            throw new Problem(404, 'not_found');
        }
        $item = $this->store->em()->find(ServiceRequest::class, $id);
        if (null === $item || !$this->permissions->visible($actor, $item)) {
            throw new Problem(404, 'not_found');
        }

        return $item;
    }

    /** @return list<array<string,mixed>> */
    public function page(Principal $actor, int $page, int $limit, ?string $state): array
    {
        $q = $this->store->em()->createQueryBuilder()->select('r')->from(ServiceRequest::class, 'r')
            ->orderBy('r.submittedAt', 'DESC')->addOrderBy('r.id', 'ASC')->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        if (Role::Requester === $actor->role()) {
            $q->andWhere('r.requester = :actor')->setParameter('actor', $actor);
        }
        if (Role::Technician === $actor->role()) {
            $q->andWhere('r.technician = :actor')->setParameter('actor', $actor);
        }
        if (null !== $state) {
            $q->andWhere('r.state = :state')->setParameter('state', $state);
        }
        /** @var list<ServiceRequest> $items */
        $items = $q->getQuery()->getResult();

        return array_map(static fn (ServiceRequest $r): array => $r->view(), $items);
    }

    /** @return list<array<string,mixed>> */
    public function history(ServiceRequest $item, int $page, int $limit): array
    {
        return array_map(static fn (AuditEntry $e): array => $e->view(), $this->store->em()->getRepository(AuditEntry::class)->findBy(['targetId' => $item->id()], ['occurredAt' => 'ASC', 'id' => 'ASC'], $limit, ($page - 1) * $limit));
    }
}
