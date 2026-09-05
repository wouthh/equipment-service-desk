<?php

declare(strict_types=1);

namespace App\Audit\Application;

use App\Access\Domain\Principal;
use App\Audit\Domain\AuditEntry;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;

final readonly class AuditWriter
{
    public function __construct(private Store $store, private Ids $ids, private ClockInterface $clock)
    {
    }

    /** @param array<string,scalar|null> $details */
    public function record(Principal $actor, string $target, string $action, array $details, string $correlationId): void
    {
        $this->store->em()->persist(new AuditEntry($this->ids->next(), $actor, $target, $action, $details, $this->clock->now(), $correlationId));
    }

    /** @param array<string,scalar|null> $details */
    public function localOperator(string $target, string $action, array $details = []): void
    {
        if (!str_starts_with($action, 'local.')) {
            throw new \LogicException('Local operator events require an explicit namespace.');
        }
        $this->store->em()->persist(new AuditEntry($this->ids->next(), null, $target, $action, $details, $this->clock->now(), $this->ids->next()));
    }
}
