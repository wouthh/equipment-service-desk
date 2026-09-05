<?php

declare(strict_types=1);

namespace App\Reporting\Application;

use App\Shared\Infrastructure\Rows;

final class CsvReport
{
    /**
     * @param iterable<array<string,mixed>> $rows
     *
     * @return array{csv:string,count:int}
     */
    public function generate(iterable $rows, \DateTimeImmutable $generatedAt): array
    {
        $stream = fopen('php://temp', 'w+');
        if (false === $stream) {
            throw new \RuntimeException('Report buffer unavailable.');
        }
        try {
            fputcsv($stream, ['request_id', 'equipment_tag', 'state', 'priority', 'submitted_at', 'due_at', 'resolved_at', 'resolution_seconds', 'overdue_at_generation', 'resolved_late'], ',', '"', '', "\r\n");
            $count = 0;
            foreach ($rows as $row) {
                if (++$count > 10000) {
                    throw new ReportLimit();
                }
                $submitted = new \DateTimeImmutable(Rows::text($row, 'submitted_at'));
                $resolved = is_string($row['resolved_at'] ?? null) ? new \DateTimeImmutable($row['resolved_at']) : null;
                $due = is_string($row['due_at'] ?? null) ? new \DateTimeImmutable($row['due_at']) : null;
                $state = Rows::text($row, 'state');
                $values = [Rows::text($row, 'id'), Rows::text($row, 'asset_tag'), $state, is_string($row['priority'] ?? null) ? $row['priority'] : '',
                    $submitted->format(DATE_ATOM), $due?->format(DATE_ATOM) ?? '', $resolved?->format(DATE_ATOM) ?? '',
                    null === $resolved ? '' : (string) ($resolved->getTimestamp() - $submitted->getTimestamp()),
                    null !== $due && $generatedAt > $due && !in_array($state, ['resolved', 'cancelled'], true) ? 'true' : 'false',
                    null !== $due && null !== $resolved && $resolved > $due ? 'true' : 'false'];
                $values = array_map(self::safeCell(...), $values);
                fputcsv($stream, $values, ',', '"', '', "\r\n");
                if (ftell($stream) > 5242880) {
                    throw new ReportLimit();
                }
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            if (false === $csv) {
                throw new \RuntimeException('Report buffer unavailable.');
            }

            return ['csv' => $csv, 'count' => $count];
        } finally {
            fclose($stream);
        }
    }

    public static function safeCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@-]/', $value) || preg_match('/^[\t\r\n]/', $value) ? "'".$value : $value;
    }
}
