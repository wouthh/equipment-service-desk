<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Access\Infrastructure\TokenIssue;
use App\Access\Infrastructure\TokenRevoke;
use App\Access\Infrastructure\UserCreate;
use App\Access\Infrastructure\UserDisable;
use App\Audit\Application\AuditWriter;
use App\Shared\Infrastructure\RandomIds;
use App\Shared\Infrastructure\Store;
use App\Tests\Support\DatabaseTest;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleAuditTest extends DatabaseTest
{
    public function testConsoleChangesAreAuditedWithoutDuplicateRevocationOrDisable(): void
    {
        $create = self::getContainer()->get(UserCreate::class);
        $disable = self::getContainer()->get(UserDisable::class);
        $revoke = self::getContainer()->get(TokenRevoke::class);
        self::assertInstanceOf(UserCreate::class, $create);
        self::assertInstanceOf(UserDisable::class, $disable);
        self::assertInstanceOf(TokenRevoke::class, $revoke);
        self::assertSame(0, (new CommandTester($create))->execute(['handle' => 'synthetic-new', 'role' => 'requester', 'label' => 'Synthetic account']));
        $disableTest = new CommandTester($disable);
        self::assertSame(0, $disableTest->execute(['handle' => 'synthetic-new']));
        self::assertSame(0, $disableTest->execute(['handle' => 'synthetic-new']));
        $tokenId = $this->admin->fetchOne('SELECT id FROM api_token ORDER BY id LIMIT 1');
        self::assertIsString($tokenId);
        $revokeTest = new CommandTester($revoke);
        self::assertSame(0, $revokeTest->execute(['id' => $tokenId]));
        self::assertSame(0, $revokeTest->execute(['id' => $tokenId]));
        self::assertSame(['local.token_revoked', 'local.user_created', 'local.user_disabled'], $this->admin->fetchFirstColumn('SELECT action FROM audit_entry ORDER BY action'));
        self::assertSame(0, $this->admin->fetchOne('SELECT count(*) FROM audit_entry WHERE actor_id IS NOT NULL'));
    }

    public function testIssuanceWritesProtectedFileAndValueFreeAudit(): void
    {
        $store = self::getContainer()->get(Store::class);
        $audit = self::getContainer()->get(AuditWriter::class);
        self::assertInstanceOf(Store::class, $store);
        self::assertInstanceOf(AuditWriter::class, $audit);
        $root = sys_get_temp_dir().'/esd-token-test-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root, 0700));
        $path = null;
        try {
            $test = new CommandTester(new TokenIssue($store, new RandomIds(), new MockClock('2026-01-01T00:00:00Z'), $audit, $root));
            self::assertSame(0, $test->execute(['actor' => 'coordinator-a']));
            $entry = $this->admin->fetchAssociative("SELECT target_id, actor_id, details FROM audit_entry WHERE action='local.token_issued'");
            self::assertIsArray($entry);
            self::assertIsString($entry['target_id']);
            self::assertIsString($entry['details']);
            self::assertNull($entry['actor_id']);
            $path = $root.'/var/local/tokens/'.$entry['target_id'].'.curl';
            self::assertSame(0600, fileperms($path) & 0777);
            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertSame(1, preg_match('/Bearer ([a-f0-9]{64})/', $contents, $match));
            if (!isset($match[1])) {
                throw new \LogicException('Synthetic token file has an invalid shape.');
            }
            self::assertStringNotContainsString($match[1], $test->getDisplay());
            self::assertStringNotContainsString($match[1], $entry['details']);
            self::assertSame(['principalId' => '10000000-0000-7000-8000-000000000003', 'lifetimeSeconds' => 3600], json_decode($entry['details'], true, 8, JSON_THROW_ON_ERROR));
            self::assertSame(hash('sha256', $match[1]), $this->admin->fetchOne('SELECT digest FROM api_token WHERE id=?', [$entry['target_id']]));
        } finally {
            if (null !== $path && is_file($path)) {
                unlink($path);
            }
            foreach (['/var/local/tokens', '/var/local', '/var', ''] as $suffix) {
                if (is_dir($root.$suffix)) {
                    rmdir($root.$suffix);
                }
            }
        }
    }

    public function testDatabaseRejectsPartialResolution(): void
    {
        $id = $this->insertRequest('assigned');
        try {
            $this->admin->executeStatement('UPDATE service_request SET resolution=? WHERE id=?', ['Partial result', $id]);
            self::fail('A nonterminal request must not contain a partial resolution.');
        } catch (DriverException $error) {
            self::assertSame('23514', $error->getSQLState());
        }
        self::assertNull($this->admin->fetchOne('SELECT resolution FROM service_request WHERE id=?', [$id]));
    }
}
