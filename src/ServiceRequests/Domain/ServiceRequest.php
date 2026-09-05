<?php

declare(strict_types=1);

namespace App\ServiceRequests\Domain;

use App\Access\Domain\Principal;
use App\Equipment\Domain\Equipment;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'service_request')]
#[ORM\Index(columns: ['requester_id', 'submitted_at'], name: 'request_owner_date')]
#[ORM\Index(columns: ['technician_id', 'state'], name: 'request_assignee_state')]
#[ORM\Index(columns: ['submitted_at'], name: 'request_report_date')]
class ServiceRequest
{
    #[ORM\Column(length: 16)]
    private string $state = 'submitted';
    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;
    /** @var array{policyVersion:string,criticality:string,impact:string,priority:string,targetSeconds:int,dueAt:string}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $triage = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
    private ?Principal $technician = null;
    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $resolution = null;
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $cancellationReason = null;
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function __construct(
        #[ORM\Id, ORM\Column(type: 'guid')] private string $id,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private Equipment $equipment,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private Principal $requester,
        #[ORM\Column(length: 120)] private string $title,
        #[ORM\Column(length: 4000)] private string $description,
        #[ORM\Column(length: 16, enumType: Impact::class)] private Impact $reportedImpact,
        #[ORM\Column(type: 'datetimetz_immutable')] private \DateTimeImmutable $submittedAt,
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

    public function technician(): ?Principal
    {
        return $this->technician;
    }

    public function equipment(): Equipment
    {
        return $this->equipment;
    }

    public function submittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = RequestState::from($state)->value;
    }

    public function etag(): string
    {
        return '"'.$this->id.':'.$this->version.'"';
    }

    public function triage(TriageDecision $decision): void
    {
        if (null !== $this->triage) {
            throw new \LogicException('A triage snapshot cannot be replaced.');
        }
        $this->triage = [
            'policyVersion' => $decision->policyVersion, 'criticality' => $decision->criticality->value,
            'impact' => $decision->impact->value, 'priority' => $decision->priority->value,
            'targetSeconds' => $decision->targetSeconds, 'dueAt' => $decision->dueAt->format(DATE_ATOM),
        ];
    }

    public function assign(Principal $technician): void
    {
        $this->technician = $technician;
    }

    public function resolve(string $summary, \DateTimeImmutable $now): void
    {
        $this->resolution = $summary;
        $this->resolvedAt = $now;
    }

    public function cancel(string $reason, \DateTimeImmutable $now): void
    {
        $this->cancellationReason = $reason;
        $this->cancelledAt = $now;
    }

    /** @return array<string,mixed> */
    public function view(): array
    {
        return [
            'id' => $this->id, 'equipmentId' => $this->equipment->id(), 'requesterId' => $this->requester->id(),
            'title' => $this->title, 'description' => $this->description, 'reportedImpact' => $this->reportedImpact->value,
            'state' => $this->state, 'version' => $this->version, 'submittedAt' => $this->submittedAt->format(DATE_ATOM),
            'triage' => $this->triage, 'technicianId' => $this->technician?->id(), 'resolution' => $this->resolution,
            'resolvedAt' => $this->resolvedAt?->format(DATE_ATOM), 'cancellationReason' => $this->cancellationReason,
            'cancelledAt' => $this->cancelledAt?->format(DATE_ATOM),
        ];
    }
}
