<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure;

use App\Reporting\Message\GenerateReport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class ReportSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        try {
            if (strlen($encodedEnvelope['body']) > 256) {
                throw new \InvalidArgumentException();
            }
            $data = json_decode($encodedEnvelope['body'], true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_keys($data) !== ['type', 'version', 'reportId'] || 'generate_report' !== $data['type'] || 1 !== $data['version'] || !is_string($data['reportId'])) {
                throw new \InvalidArgumentException();
            }
            $retry = $encodedEnvelope['headers']['retry'] ?? '0';
            if (!preg_match('/^[0-3]$/D', $retry)) {
                throw new \InvalidArgumentException();
            }

            return new Envelope(new GenerateReport($data['reportId']), [new RedeliveryStamp((int) $retry)]);
        } catch (\Throwable) {
            throw new MessageDecodingFailedException('Unsupported report message.');
        }
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        if (!$message instanceof GenerateReport) {
            throw new \InvalidArgumentException('Unsupported message.');
        }
        $retry = $envelope->last(RedeliveryStamp::class);

        return ['body' => json_encode(['type' => 'generate_report', 'version' => 1, 'reportId' => $message->reportId], JSON_THROW_ON_ERROR), 'headers' => ['retry' => (string) ($retry?->getRetryCount() ?? 0)]];
    }
}
