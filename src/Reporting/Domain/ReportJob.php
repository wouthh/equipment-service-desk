<?php

declare(strict_types=1);

namespace App\Reporting\Domain;

use App\Access\Domain\Principal;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'report_job')]
#[ORM\UniqueConstraint(name: 'one_pending_report', columns: ['requester_id'], options: ['where' => "((status)::text = 'pending'::text)"])]
class ReportJob
{
    #[ORM\Column(length: 16)] private string $status = 'pending';
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)] private ?\DateTimeImmutable $generatedAt = null;
    #[ORM\Column(type: 'integer', nullable: true)] private ?int $rowCount = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $csv = null;
    #[ORM\Column(length: 48, nullable: true)] private ?string $failureCode = null;
    public function __construct(
        #[ORM\Id, ORM\Column(type: 'guid')] private string $id,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private Principal $requester,
        #[ORM\Column(type: 'datetimetz_immutable')] private \DateTimeImmutable $fromAt,
        #[ORM\Column(type: 'datetimetz_immutable')] private \DateTimeImmutable $toAt,
        #[ORM\Column(type: 'datetimetz_immutable')] private \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function requester(): Principal
    {
        return $this->requester;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function criteria(): ReportCriteria
    {
        return new ReportCriteria($this->fromAt, $this->toAt);
    }

    public function csv(): ?string
    {
        return $this->csv;
    }

    /** @return array<string,mixed> */
    public function view(): array
    {
        return ['id' => $this->id, 'status' => $this->status, 'from' => $this->fromAt->format(DATE_ATOM), 'to' => $this->toAt->format(DATE_ATOM), 'createdAt' => $this->createdAt->format(DATE_ATOM), 'generatedAt' => $this->generatedAt?->format(DATE_ATOM), 'rowCount' => $this->rowCount, 'bytes' => null === $this->csv ? null : strlen($this->csv), 'failureCode' => $this->failureCode];
    }
}
