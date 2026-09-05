<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\DatabaseTest;

final class ConcurrencyTest extends DatabaseTest
{
    public function testTwoIndependentAssignmentsCannotOverwrite(): void
    {
        $id = $this->insertRequest('triaged');
        $processes = [];
        $channels = [];
        try {
            foreach ([5, 6] as $number) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, dirname(__DIR__).'/Support/concurrency.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                foreach ($pipes as $pipe) {
                    stream_set_timeout($pipe, 15);
                }
                $processes[] = $process;
                $channels[] = $pipes;
                fwrite($pipes[0], json_encode(['id' => $id, 'technician' => sprintf('10000000-0000-7000-8000-%012d', $number)], JSON_THROW_ON_ERROR)."\n");
            }
            // Both children have independently hydrated exactly version 1 before either can write.
            foreach ($channels as $pipes) {
                self::assertSame("READY\n", fgets($pipes[1]));
            }
            foreach ($channels as $pipes) {
                fwrite($pipes[0], "GO\n");
                fflush($pipes[0]);
            }
            $results = [];
            foreach ($channels as $pipes) {
                $results[] = fgets($pipes[1]);
            }
            sort($results);
            self::assertSame(["200\n", "412\n"], $results);
            self::assertSame(2, $this->admin->fetchOne('SELECT version FROM service_request WHERE id=?', [$id]));
            self::assertSame(1, $this->recordCount('audit_entry'));
        } finally {
            foreach ($channels as $pipes) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
            }
            foreach ($processes as $process) {
                self::assertSame(0, proc_close($process));
            }
        }
    }
}
