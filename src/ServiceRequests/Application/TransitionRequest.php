<?php

declare(strict_types=1);

namespace App\ServiceRequests\Application;

use App\Access\Application\Directory;
use App\Access\Application\Permissions;
use App\Access\Domain\Principal;
use App\Access\Domain\Role;
use App\Audit\Application\AuditWriter;
use App\ServiceRequests\Application\Input\AssignmentInput;
use App\ServiceRequests\Application\Input\CancellationInput;
use App\ServiceRequests\Application\Input\ResolutionInput;
use App\ServiceRequests\Application\Input\TriageInput;
use App\ServiceRequests\Domain\Impact;
use App\ServiceRequests\Domain\ServiceRequest;
use App\ServiceRequests\Domain\TriagePolicy;
use App\Shared\Http\Problem;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class TransitionRequest
{
    public function __construct(
        private Store $store, private Directory $directory, private Permissions $permissions,
        private TriagePolicy $policy, private AuditWriter $audit, private ClockInterface $clock,
        #[Autowire(service: 'state_machine.service_request')] private WorkflowInterface $workflow,
    ) {
    }

    public function execute(Principal $actor, ServiceRequest $item, string $action, TriageInput|AssignmentInput|ResolutionInput|CancellationInput $input, string $etag, string $correlation): ServiceRequest
    {
        $this->permissions->transition($actor, $item, $action);
        if ($etag !== $item->etag()) {
            throw new Problem(412, 'stale_version');
        }
        $transition = match ($action) {
            'assignment' => 'assigned' === $item->getState() ? 'reassign' : 'assign', 'resolution' => 'resolve', 'cancellation' => 'cancel', default => 'triage',
        };
        if ('cancellation' === $action && Role::Requester === $actor->role() && 'submitted' !== $item->getState()) {
            throw new Problem(403, 'forbidden');
        }
        if (!$this->workflow->can($item, $transition)) {
            throw new Problem(409, 'invalid_transition');
        }
        $before = $item->getState();
        $details = [];
        if ($input instanceof TriageInput) {
            $decision = $this->policy->decide($item->equipment()->criticality(), Impact::from($input->impact), $item->submittedAt());
            $item->triage($decision);
            $details = ['policyVersion' => $decision->policyVersion, 'priority' => $decision->priority->value, 'impact' => $decision->impact->value];
        } elseif ($input instanceof AssignmentInput) {
            $technician = $this->directory->technician($input->technicianId);
            if ($technician->id() === $item->technician()?->id()) {
                throw new Problem(409, 'assignment_unchanged');
            }
            $details = ['previousTechnicianId' => $item->technician()?->id(), 'technicianId' => $technician->id()];
            $item->assign($technician);
        } elseif ($input instanceof ResolutionInput) {
            $item->resolve(trim($input->summary), $this->clock->now());
        } else {
            $item->cancel(trim($input->reason), $this->clock->now());
        }
        $this->workflow->apply($item, $transition);
        $this->audit->record($actor, $item->id(), 'request.'.$transition, ['from' => $before, 'to' => $item->getState(), ...$details], $correlation);
        $this->store->em()->flush();

        return $item;
    }
}
