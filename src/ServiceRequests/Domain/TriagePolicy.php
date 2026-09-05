<?php

declare(strict_types=1);

namespace App\ServiceRequests\Domain;

use App\Equipment\Domain\Criticality;

interface TriagePolicy
{
    public function decide(Criticality $criticality, Impact $impact, \DateTimeImmutable $submittedAt): TriageDecision;
}
