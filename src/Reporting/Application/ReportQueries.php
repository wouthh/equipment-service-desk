<?php

declare(strict_types=1);

namespace App\Reporting\Application;

use App\Access\Application\Permissions;
use App\Access\Domain\Principal;
use App\Access\Domain\Role;
use App\Reporting\Domain\ReportJob;
use App\Shared\Http\Problem;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Uid\Uuid;

final readonly class ReportQueries
{
    public function __construct(private Store $store, private Permissions $permissions)
    {
    }

    public function get(string $id, Principal $actor): ReportJob
    {
        $this->permissions->role($actor, Role::Coordinator);
        $job = Uuid::isValid($id) ? $this->store->em()->find(ReportJob::class, $id) : null;
        if (null === $job) {
            throw new Problem(404, 'not_found');
        }

        return $job;
    }
}
