<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905000500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identify trusted local operator audit events and reject partial lifecycle results.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_entry ALTER COLUMN actor_id DROP NOT NULL');
        $this->addSql("ALTER TABLE audit_entry ADD CONSTRAINT audit_actor_kind CHECK ((actor_id IS NULL) = (action LIKE 'local.%'))");
        $this->addSql("ALTER TABLE service_request ADD CONSTRAINT resolution_complete CHECK (state = 'resolved' OR (resolution IS NULL AND resolved_at IS NULL))");
        $this->addSql("ALTER TABLE service_request ADD CONSTRAINT cancellation_complete CHECK (state = 'cancelled' OR (cancellation_reason IS NULL AND cancelled_at IS NULL))");
        $this->addSql("ALTER TABLE service_request ADD CONSTRAINT untriaged_fields CHECK (state <> 'submitted' OR (triage IS NULL AND technician_id IS NULL))");
        $this->addSql("ALTER TABLE service_request ADD CONSTRAINT unassigned_fields CHECK (state <> 'triaged' OR technician_id IS NULL)");
        $this->addSql("ALTER TABLE report_job ADD CONSTRAINT report_result_complete CHECK (status = 'ready' OR (csv IS NULL AND generated_at IS NULL AND row_count IS NULL))");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Preserve operator audit history; use a reviewed forward correction.');
    }
}
