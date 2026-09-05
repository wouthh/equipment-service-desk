<?php

declare(strict_types=1);

namespace App\ServiceRequests\Domain;

use App\Equipment\Domain\Criticality;

final readonly class TriageDecision
{
    public function __construct(
        public string $policyVersion,
        public Criticality $criticality,
        public Impact $impact,
        public Priority $priority,
        public int $targetSeconds,
        public \DateTimeImmutable $dueAt,
    ) {
    }
}
