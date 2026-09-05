<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\Principal;
use App\Access\Domain\Role;
use App\ServiceRequests\Domain\ServiceRequest;
use App\Shared\Http\Problem;

final class Permissions
{
    public function role(Principal $actor, Role $required): void
    {
        if (!$actor->active() || $actor->role() !== $required) {
            throw new Problem(403, 'forbidden');
        }
    }

    public function visible(Principal $actor, ServiceRequest $request): bool
    {
        return $actor->active() && match ($actor->role()) {
            Role::Coordinator => true,
            Role::Requester => $request->requester()->id() === $actor->id(),
            Role::Technician => $request->technician()?->id() === $actor->id(),
        };
    }

    public function transition(Principal $actor, ServiceRequest $request, string $action): void
    {
        if (!$this->visible($actor, $request)) {
            throw new Problem(404, 'not_found');
        }
        $allowed = match ($action) {
            'triage','assignment' => Role::Coordinator === $actor->role(),
            'resolution' => Role::Technician === $actor->role() && $request->technician()?->id() === $actor->id(),
            'cancellation' => Role::Coordinator === $actor->role() || Role::Requester === $actor->role(),
            default => false,
        };
        if (!$allowed) {
            throw new Problem(403, 'forbidden');
        }
        // State-specific cancellation belongs to execution, not replay authorization.
    }
}
