<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Core access, equipment, guarded requests, audit and idempotency.';
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE app_user (active BOOLEAN DEFAULT true NOT NULL, id UUID NOT NULL PRIMARY KEY, handle VARCHAR(48) NOT NULL, label VARCHAR(80) NOT NULL, role VARCHAR(20) NOT NULL CHECK (role IN ('requester','coordinator','technician')));
CREATE UNIQUE INDEX UNIQ_88BDF3E9918020D9 ON app_user (handle);
CREATE TABLE api_token (revoked_at TIMESTAMPTZ DEFAULT NULL, id UUID PRIMARY KEY, digest VARCHAR(64) NOT NULL, expires_at TIMESTAMPTZ NOT NULL, principal_id UUID NOT NULL REFERENCES app_user(id));
CREATE UNIQUE INDEX UNIQ_7BA2F5EBD12A0B2 ON api_token (digest);
CREATE INDEX IDX_7BA2F5EB474870EE ON api_token (principal_id);
CREATE TABLE equipment (id UUID PRIMARY KEY, asset_tag VARCHAR(32) NOT NULL, name VARCHAR(100) NOT NULL, criticality VARCHAR(16) NOT NULL CHECK (criticality IN ('normal','critical')), registered_at TIMESTAMPTZ NOT NULL);
CREATE UNIQUE INDEX UNIQ_D338D5836983740F ON equipment (asset_tag);
CREATE TABLE service_request (
 state VARCHAR(16) NOT NULL CHECK (state IN ('submitted','triaged','assigned','resolved','cancelled')),
 version INT DEFAULT 1 NOT NULL CHECK (version > 0), triage JSON DEFAULT NULL, resolution VARCHAR(2000) DEFAULT NULL,
 resolved_at TIMESTAMPTZ DEFAULT NULL, cancellation_reason VARCHAR(500) DEFAULT NULL, cancelled_at TIMESTAMPTZ DEFAULT NULL,
 id UUID PRIMARY KEY, title VARCHAR(120) NOT NULL, description VARCHAR(4000) NOT NULL,
 reported_impact VARCHAR(16) NOT NULL CHECK (reported_impact IN ('stopped','degraded')), submitted_at TIMESTAMPTZ NOT NULL,
 technician_id UUID DEFAULT NULL REFERENCES app_user(id), equipment_id UUID NOT NULL REFERENCES equipment(id),
 requester_id UUID NOT NULL REFERENCES app_user(id),
 CHECK (state NOT IN ('triaged','assigned','resolved') OR triage IS NOT NULL),
 CHECK (state NOT IN ('assigned','resolved') OR technician_id IS NOT NULL),
 CHECK ((state='resolved') = (resolution IS NOT NULL AND resolved_at IS NOT NULL)),
 CHECK ((state='cancelled') = (cancellation_reason IS NOT NULL AND cancelled_at IS NOT NULL))
);
CREATE INDEX IDX_F413DD03E6C5D496 ON service_request (technician_id);
CREATE INDEX IDX_F413DD03517FE9FE ON service_request (equipment_id);
CREATE INDEX IDX_F413DD03ED442CF4 ON service_request (requester_id);
CREATE INDEX request_owner_date ON service_request (requester_id, submitted_at);
CREATE INDEX request_assignee_state ON service_request (technician_id, state);
CREATE INDEX request_report_date ON service_request (submitted_at);
CREATE TABLE audit_entry (id UUID PRIMARY KEY, target_id UUID NOT NULL, action VARCHAR(48) NOT NULL, details JSON NOT NULL, occurred_at TIMESTAMPTZ NOT NULL, correlation_id UUID NOT NULL, actor_id UUID NOT NULL REFERENCES app_user(id));
CREATE INDEX IDX_2C6AF98710DAF24A ON audit_entry (actor_id);
CREATE INDEX audit_target_date ON audit_entry (target_id, occurred_at);
CREATE TABLE idempotency_record (id UUID PRIMARY KEY, key_digest VARCHAR(64) NOT NULL, fingerprint VARCHAR(64) NOT NULL, response JSON DEFAULT NULL, created_at TIMESTAMPTZ NOT NULL, principal_id UUID NOT NULL REFERENCES app_user(id));
CREATE INDEX IDX_A68190E5474870EE ON idempotency_record (principal_id);
CREATE UNIQUE INDEX idempotency_actor_key ON idempotency_record (principal_id, key_digest);
GRANT SELECT, INSERT, UPDATE, DELETE ON app_user, api_token, equipment, service_request, idempotency_record TO desk_runtime;
GRANT SELECT, INSERT ON audit_entry TO desk_runtime;
SQL;
        foreach (explode(';', $sql) as $statement) {
            if ('' !== trim($statement)) {
                $this->addSql(trim($statement));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Core rollback would destroy records. Restore a verified local backup instead.');
    }
}
