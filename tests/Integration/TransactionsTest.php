<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Access\Domain\Principal;
use App\Reporting\Application\CsvReport;
use App\Reporting\Application\GenerateReportHandler;
use App\Reporting\Application\RequestReport;
use App\Reporting\Domain\ReportCriteria;
use App\Reporting\Infrastructure\ReportFailure;
use App\Reporting\Message\GenerateReport;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Store;
use App\Tests\Support\DatabaseTest;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class TransactionsTest extends DatabaseTest
{
    private function store(): Store
    {
        $s = self::getContainer()->get(Store::class);
        self::assertInstanceOf(Store::class, $s);

        return $s;
    }

    private function requestReport(): string
    {
        $actor = $this->store()->em()->find(Principal::class, '10000000-0000-7000-8000-000000000003');
        self::assertInstanceOf(Principal::class, $actor);
        $create = self::getContainer()->get(RequestReport::class);
        self::assertInstanceOf(RequestReport::class, $create);

        return $create->execute($actor, ReportCriteria::parse('2026-01-01T00:00:00Z', '2026-01-03T00:00:00Z'), $this->newKey())->id();
    }

    public function testReportQueueSharesBusinessTransaction(): void
    {
        $db = $this->store()->em()->getConnection();
        $db->beginTransaction();
        $this->requestReport();
        self::assertSame(1, $db->fetchOne('SELECT count(*) FROM report_job'));
        self::assertSame(1, $db->fetchOne('SELECT count(*) FROM messenger_messages'));
        self::assertSame(0, $this->recordCount('messenger_messages'));
        self::assertSame(0, $this->recordCount('report_job'));
        $db->rollBack();
        $this->store()->em()->clear();
        self::assertSame(0, $this->recordCount('messenger_messages'));
        self::assertSame(0, $this->recordCount('report_job'));
        self::assertSame(0, $this->recordCount('audit_entry'));
    }

    public function testFailureBeforeCommitRollsBackResultThenRedeliveryCompletes(): void
    {
        $this->insertRequest('resolved');
        $id = $this->store()->em()->getConnection()->transactional(fn (): string => $this->requestReport());
        $ids = new class implements Ids {
            public function next(): string
            {
                throw new \RuntimeException('Synthetic failpoint before commit.');
            }
        };
        $broken = new GenerateReportHandler($this->store(), new CsvReport(), new MockClock('2026-01-04T00:00:00Z'), $ids);
        try {
            $broken(new GenerateReport($id));
            self::fail('Expected synthetic failpoint.');
        } catch (\RuntimeException $e) {
            self::assertSame('Synthetic failpoint before commit.', $e->getMessage());
        }
        self::assertSame('pending', $this->admin->fetchOne('SELECT status FROM report_job WHERE id=?', [$id]));
        self::assertNull($this->admin->fetchOne('SELECT csv FROM report_job WHERE id=?', [$id]));
        $handler = self::getContainer()->get(GenerateReportHandler::class);
        self::assertInstanceOf(GenerateReportHandler::class, $handler);
        $handler(new GenerateReport($id));
        $first = $this->admin->fetchAssociative('SELECT csv,generated_at,row_count FROM report_job WHERE id=?', [$id]);
        // Simulate lost acknowledgement: the committed message is delivered again.
        $handler(new GenerateReport($id));
        self::assertSame($first, $this->admin->fetchAssociative('SELECT csv,generated_at,row_count FROM report_job WHERE id=?', [$id]));
        self::assertSame(2, $this->recordCount('audit_entry'));
    }

    public function testAuthorizationDriftAndTerminalFailure(): void
    {
        $id = $this->store()->em()->getConnection()->transactional(fn (): string => $this->requestReport());
        $this->admin->executeStatement("UPDATE app_user SET active=false WHERE handle='coordinator-a'");
        $handler = self::getContainer()->get(GenerateReportHandler::class);
        self::assertInstanceOf(GenerateReportHandler::class, $handler);
        try {
            $handler(new GenerateReport($id));
            self::fail('Expected authorization denial.');
        } catch (UnrecoverableMessageHandlingException) {
            self::assertNull($this->admin->fetchOne('SELECT csv FROM report_job WHERE id=?', [$id]));
        }
        $failure = self::getContainer()->get(ReportFailure::class);
        self::assertInstanceOf(ReportFailure::class, $failure);
        $event = new WorkerMessageFailedEvent(new Envelope(new GenerateReport($id)), 'reports', new \RuntimeException('Synthetic failure.'));
        $retry = new WorkerMessageFailedEvent($event->getEnvelope(), 'reports', $event->getThrowable());
        $retry->setForRetry();
        $failure($retry);
        self::assertSame('pending', $this->admin->fetchOne('SELECT status FROM report_job WHERE id=?', [$id]));
        $failure($event);
        $failure($event);
        self::assertSame('failed', $this->admin->fetchOne('SELECT status FROM report_job WHERE id=?', [$id]));
        self::assertSame(2, $this->recordCount('audit_entry'));
    }

    public function testRuntimeRoleCannotChangeSchemaOrAuditHistory(): void
    {
        $db = $this->store()->em()->getConnection();
        self::assertFalse($db->fetchOne("SELECT has_schema_privilege(current_user,'public','CREATE')"));
        self::assertFalse($db->fetchOne("SELECT has_table_privilege(current_user,'audit_entry','DELETE')"));
        self::assertFalse($db->fetchOne("SELECT has_table_privilege(current_user,'audit_entry','UPDATE')"));
        self::assertTrue($db->fetchOne("SELECT has_table_privilege(current_user,'audit_entry','INSERT')"));
    }
}
