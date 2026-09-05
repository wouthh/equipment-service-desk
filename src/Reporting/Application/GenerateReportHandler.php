<?php

declare(strict_types=1);

namespace App\Reporting\Application;

use App\Reporting\Message\GenerateReport;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Rows;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class GenerateReportHandler
{
    public function __construct(private Store $store, private CsvReport $csv, private ClockInterface $clock, private Ids $ids)
    {
    }

    public function __invoke(GenerateReport $message): void
    {
        $db = $this->store->em()->getConnection();
        try {
            $db->transactional(function () use ($db, $message): void {
                $db->executeStatement("SET LOCAL statement_timeout = '20s'");
                $db->executeStatement("SET LOCAL lock_timeout = '5s'");
                $row = $db->fetchAssociative('SELECT * FROM report_job WHERE id=? FOR UPDATE', [$message->reportId]);
                if (false === $row) {
                    throw new UnrecoverableMessageHandlingException('Report unavailable.');
                }
                if ('pending' !== $row['status']) {
                    return;
                }
                // Keep account authority stable until the generated result commits.
                $actor = $db->fetchAssociative('SELECT active,role FROM app_user WHERE id=? FOR SHARE', [Rows::text($row, 'requester_id')]);
                if (false === $actor || true !== $actor['active'] || 'coordinator' !== $actor['role']) {
                    throw new UnrecoverableMessageHandlingException('Report authorization unavailable.');
                }
                $now = $this->clock->now();
                // A single bounded SELECT has one PostgreSQL statement snapshot.
                $result = $db->executeQuery("SELECT r.id,e.asset_tag,r.state,r.triage->>'priority' AS priority,r.submitted_at,r.triage->>'dueAt' AS due_at,r.resolved_at FROM service_request r JOIN equipment e ON e.id=r.equipment_id WHERE r.submitted_at>=? AND r.submitted_at<? ORDER BY r.submitted_at,r.id LIMIT 10001", [Rows::text($row, 'from_at'), Rows::text($row, 'to_at')]);
                $output = $this->csv->generate($result->iterateAssociative(), $now);
                $db->executeStatement("UPDATE report_job SET status='ready',csv=?,row_count=?,generated_at=? WHERE id=?", [$output['csv'], $output['count'], $now->format(DATE_ATOM), $message->reportId]);
                $db->executeStatement('INSERT INTO audit_entry (id,actor_id,target_id,action,details,occurred_at,correlation_id) VALUES (?,?,?,?,?,?,?)',
                    [$this->ids->next(), Rows::text($row, 'requester_id'), $message->reportId, 'report_ready', json_encode(['rows' => $output['count']], JSON_THROW_ON_ERROR), $now->format(DATE_ATOM), $this->ids->next()]);
            });
        } catch (ReportLimit) {
            throw new UnrecoverableMessageHandlingException('Report exceeds supported bounds.');
        }
    }
}
