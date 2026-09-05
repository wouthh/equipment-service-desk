<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Access\Domain\Principal;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_entry')]
#[ORM\Index(columns: ['target_id', 'occurred_at'], name: 'audit_target_date')]
class AuditEntry
{
    /** @param array<string,scalar|null> $details */
    public function __construct(
        #[ORM\Id, ORM\Column(type: 'guid')] private string $id,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)] private ?Principal $actor,
        #[ORM\Column(type: 'guid')] private string $targetId,
        #[ORM\Column(length: 48)] private string $action,
        #[ORM\Column(type: 'json')] private array $details,
        #[ORM\Column(type: 'datetimetz_immutable')] private \DateTimeImmutable $occurredAt,
        #[ORM\Column(type: 'guid')] private string $correlationId,
    ) {
    }

    /** @return array<string,mixed> */
    public function view(): array
    {
        return ['id' => $this->id, 'actorId' => $this->actor?->id(), 'action' => $this->action, 'details' => $this->details, 'occurredAt' => $this->occurredAt->format(DATE_ATOM), 'correlationId' => $this->correlationId];
    }
}
