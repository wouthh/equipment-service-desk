<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure;

use App\Reporting\Message\GenerateReport;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Rows;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener(event: WorkerMessageFailedEvent::class, priority: -50)]
final readonly class ReportFailure
{
    public function __construct(private Store $store, private Ids $ids, private ClockInterface $clock)
    {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if ($event->willRetry() || !$message instanceof GenerateReport) {
            return;
        }
        $db = $this->store->em()->getConnection();
        $db->transactional(function () use ($db, $message): void {
            $row = $db->fetchAssociative('SELECT requester_id,status FROM report_job WHERE id=? FOR UPDATE', [$message->reportId]);
            if (false === $row || 'pending' !== $row['status']) {
                return;
            }
            $db->executeStatement("UPDATE report_job SET status='failed',failure_code='generation_failed' WHERE id=?", [$message->reportId]);
            $db->executeStatement('INSERT INTO audit_entry (id,actor_id,target_id,action,details,occurred_at,correlation_id) VALUES (?,?,?,?,?,?,?)',
                [$this->ids->next(), Rows::text($row, 'requester_id'), $message->reportId, 'report_failed', '{}', $this->clock->now()->format(DATE_ATOM), $this->ids->next()]);
        });
    }
}
