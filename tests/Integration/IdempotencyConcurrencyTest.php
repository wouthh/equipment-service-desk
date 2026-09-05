<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\DatabaseTest;

final class IdempotencyConcurrencyTest extends DatabaseTest
{
    public function testConcurrentIdenticalCommandsHaveOneEffect(): void
    {
        $processes = [];
        $channels = [];
        $key = $this->newKey();
        $pids = [];
        try {
            foreach ([true, false] as $hold) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, dirname(__DIR__).'/Support/idempotency-process.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                foreach ($pipes as $pipe) {
                    stream_set_timeout($pipe, 15);
                }
                $processes[] = $process;
                $channels[] = $pipes;
                fwrite($pipes[0], json_encode(['key' => $key, 'hold' => $hold], JSON_THROW_ON_ERROR)."\n");
                $ready = fgets($pipes[1]);
                self::assertIsString($ready);
                self::assertMatchesRegularExpression('/^READY:[0-9]+\n$/D', $ready);
                $pids[] = (int) substr($ready, 6);
            }
            fwrite($channels[0][0], "GO\n");
            self::assertSame("INSIDE\n", fgets($channels[0][1]));
            fwrite($channels[1][0], "GO\n");
            $deadline = microtime(true) + 5;
            do {
                $blocked = $this->admin->fetchOne('SELECT cardinality(pg_blocking_pids(?))', [$pids[1]]);
                if (is_int($blocked) && $blocked > 0) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertIsInt($blocked);
            self::assertGreaterThan(0, $blocked, 'Second transaction never waited on the reservation.');
            fwrite($channels[0][0], "COMMIT\n");
            self::assertSame("201:false\n", fgets($channels[0][1]));
            self::assertSame("201:true\n", fgets($channels[1][1]));
            self::assertSame(1, $this->admin->fetchOne("SELECT count(*) FROM equipment WHERE asset_tag='RACE-1'"));
            self::assertSame(1, $this->recordCount('idempotency_record'));
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
