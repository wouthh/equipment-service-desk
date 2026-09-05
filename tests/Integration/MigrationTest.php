<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\DatabaseTest;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Process\Process;

final class MigrationTest extends DatabaseTest
{
    public function testCoreDataSurvivesReportingUpgrade(): void
    {
        $url = getenv('TEST_MIGRATION_URL');
        self::assertIsString($url);
        $database = 'desk_upgrade_'.getmypid();
        $this->admin->executeStatement('CREATE DATABASE '.$database);
        $connection = null;
        try {
            $upgradeUrl = substr($url, 0, -strlen('desk_test')).$database;
            $params = (new \Doctrine\DBAL\Tools\DsnParser(['postgresql' => 'pdo_pgsql']))->parse($upgradeUrl);
            $connection = DriverManager::getConnection($params);
            /** @param list<string> $command */
            $run = function (array $command) use ($upgradeUrl): void {
                self::assertIsString($command[0]);
                $process = new Process([PHP_BINARY, 'bin/console', ...$command], dirname(__DIR__, 2), ['DATABASE_URL' => $upgradeUrl, 'APP_ENV' => 'test', 'APP_DEBUG' => '1'], null, 30);
                $process->run();
                self::assertSame(0, $process->getExitCode(), 'Isolated migration command failed: '.$command[0]);
            };
            $run(['doctrine:migrations:migrate', 'DoctrineMigrations\\Version20260905000100', '--no-interaction']);
            $run(['app:demo:seed']);
            $before = $connection->fetchAllAssociative('SELECT * FROM equipment ORDER BY id');
            self::assertCount(2, $before);
            self::assertFalse($connection->createSchemaManager()->tablesExist(['report_job']));
            $run(['doctrine:migrations:migrate', '--no-interaction']);
            self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM equipment ORDER BY id'));
            self::assertSame(6, $connection->fetchOne('SELECT count(*) FROM app_user'));
            self::assertTrue($connection->createSchemaManager()->tablesExist(['report_job', 'messenger_messages']));
            $run(['doctrine:schema:validate']);
        } finally {
            $connection?->close();
            $this->admin->executeStatement('DROP DATABASE '.$database);
        }
    }
}
