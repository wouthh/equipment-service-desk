<?php

declare(strict_types=1);

namespace App\Access\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'api_token')]
class AccessToken
{
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(
        #[ORM\Id, ORM\Column(type: 'guid')] private string $id,
        #[ORM\Column(length: 64, unique: true)] private string $digest,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private Principal $principal,
        #[ORM\Column(type: 'datetimetz_immutable')] private \DateTimeImmutable $expiresAt,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function principal(): Principal
    {
        return $this->principal;
    }

    public function validAt(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $now < $this->expiresAt && $this->principal->active();
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt = $now;
    }

    public function revoked(): bool
    {
        return null !== $this->revokedAt;
    }
}
