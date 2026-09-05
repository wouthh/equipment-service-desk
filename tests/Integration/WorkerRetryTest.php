<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Reporting\Infrastructure\ReportFailure;
use App\Reporting\Message\GenerateReport;
use App\Tests\Support\DatabaseTest;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

final class WorkerRetryTest extends DatabaseTest
{
    public function testActualTransportRetriesThreeTimesThenFails(): void
    {
        $id = $this->newKey();
        $this->admin->insert('report_job', ['id' => $id, 'requester_id' => '10000000-0000-7000-8000-000000000003', 'from_at' => '2026-01-01', 'to_at' => '2026-01-02', 'created_at' => '2026-01-01', 'status' => 'pending']);
        $reports = self::getContainer()->get('messenger.transport.reports');
        self::assertInstanceOf(TransportInterface::class, $reports);
        $failed = self::getContainer()->get('messenger.transport.failed');
        self::assertInstanceOf(TransportInterface::class, $failed);
        $failure = self::getContainer()->get(ReportFailure::class);
        self::assertInstanceOf(ReportFailure::class, $failure);
        $strategy = self::getContainer()->get('messenger.retry.multiplier_retry_strategy.reports');
        self::assertInstanceOf(MultiplierRetryStrategy::class, $strategy);
        $attempts = 0;
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([GenerateReport::class => [function (GenerateReport $message) use (&$attempts): void {
            ++$attempts;
            throw new \RuntimeException('Synthetic transient failure.');
        }]]))]);
        $events = new EventDispatcher();
        $events->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['reports' => fn (): TransportInterface => $reports]), new ServiceLocator(['reports' => fn (): MultiplierRetryStrategy => $strategy])));
        $events->addListener(WorkerMessageFailedEvent::class, $failure, -50);
        $events->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['reports' => fn (): TransportInterface => $failed])));
        $worker = new Worker(['reports' => $reports], $bus, $events);
        $events->addListener(WorkerMessageFailedEvent::class, function (WorkerMessageFailedEvent $event) use ($worker): void { if (!$event->willRetry()) { $worker->stop(); } }, -200);
        $reports->send(new Envelope(new GenerateReport($id)));
        $worker->run(['sleep' => 20000, 'time_limit' => 15]);
        self::assertSame(4, $attempts);
        self::assertSame('failed', $this->admin->fetchOne('SELECT status FROM report_job WHERE id=?', [$id]));
        self::assertSame(1, $this->admin->fetchOne("SELECT count(*) FROM messenger_messages WHERE queue_name='failed'"));
        self::assertSame(0, $this->admin->fetchOne("SELECT count(*) FROM messenger_messages WHERE queue_name='reports'"));
        self::assertNull($this->admin->fetchOne('SELECT csv FROM report_job WHERE id=?', [$id]));
    }
}
