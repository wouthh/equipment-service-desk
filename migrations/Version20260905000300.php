<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align timestamp precision with the Doctrine immutable timestamp contract.';
    }

    public function up(Schema $schema): void
    {
        foreach ([
            'api_token' => ['expires_at', 'revoked_at'], 'equipment' => ['registered_at'],
            'service_request' => ['submitted_at', 'resolved_at', 'cancelled_at'], 'audit_entry' => ['occurred_at'],
            'idempotency_record' => ['created_at'], 'report_job' => ['from_at', 'to_at', 'created_at', 'generated_at'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->addSql('ALTER TABLE '.$table.' ALTER COLUMN '.$column.' TYPE TIMESTAMP(0) WITH TIME ZONE');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Subsecond precision cannot be reconstructed. Restore a verified backup.');
    }
}
