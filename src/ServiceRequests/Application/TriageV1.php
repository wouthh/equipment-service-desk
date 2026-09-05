<?php

declare(strict_types=1);

namespace App\ServiceRequests\Application;

use App\Equipment\Domain\Criticality;
use App\ServiceRequests\Domain\Impact;
use App\ServiceRequests\Domain\Priority;
use App\ServiceRequests\Domain\TriageDecision;
use App\ServiceRequests\Domain\TriagePolicy;

final class TriageV1 implements TriagePolicy
{
    public function decide(Criticality $criticality, Impact $impact, \DateTimeImmutable $submittedAt): TriageDecision
    {
        [$priority, $hours] = match (true) {
            Criticality::Critical === $criticality && Impact::Stopped === $impact => [Priority::High, 4],
            Criticality::Critical === $criticality || Impact::Stopped === $impact => [Priority::Medium, 24],
            default => [Priority::Low, 72],
        };
        $seconds = $hours * 3600;

        return new TriageDecision('triage-v1', $criticality, $impact, $priority, $seconds, $submittedAt->setTimestamp($submittedAt->getTimestamp() + $seconds));
    }
}
