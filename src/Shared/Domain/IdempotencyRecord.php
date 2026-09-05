<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Access\Domain\Principal;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'idempotency_record')]
#[ORM\UniqueConstraint(name: 'idempotency_actor_key', columns: ['principal_id', 'key_digest'])]
class IdempotencyRecord
{
    /** @var array<string,mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)] private ?array $response = null;
    public function __construct(
        #[ORM\Id, ORM\Column(type: 'guid')] private string $id,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private Principal $principal,
        #[ORM\Column(length: 64)] private string $keyDigest,
        #[ORM\Column(length: 64)] private string $fingerprint,
        #[ORM\Column(type: 'datetimetz_immutable')] private \DateTimeImmutable $createdAt,
    ) {
    }
}
