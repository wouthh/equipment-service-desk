<?php

declare(strict_types=1);

namespace App\Reporting\Application;

use App\Access\Application\Permissions;
use App\Access\Domain\Principal;
use App\Access\Domain\Role;
use App\Audit\Application\AuditWriter;
use App\Reporting\Domain\ReportCriteria;
use App\Reporting\Domain\ReportJob;
use App\Reporting\Message\GenerateReport;
use App\Shared\Http\Problem;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RequestReport
{
    public function __construct(private Store $store, private Ids $ids, private ClockInterface $clock, private MessageBusInterface $bus, private AuditWriter $audit, private Permissions $permissions)
    {
    }

    public function execute(Principal $actor, ReportCriteria $criteria, string $correlation): ReportJob
    {
        $this->permissions->role($actor, Role::Coordinator);
        $em = $this->store->em();
        if (!$em->getConnection()->isTransactionActive()) {
            throw new \LogicException('Report creation requires a transaction.');
        }
        if (null !== $em->getRepository(ReportJob::class)->findOneBy(['requester' => $actor, 'status' => 'pending'])) {
            throw new Problem(409, 'report_pending');
        }
        $report = new ReportJob($this->ids->next(), $actor, $criteria->from, $criteria->to, $this->clock->now());
        $em->persist($report);
        $this->audit->record($actor, $report->id(), 'report_requested', [], $correlation);
        $em->flush();
        $this->bus->dispatch(new GenerateReport($report->id()));

        return $report;
    }
}
