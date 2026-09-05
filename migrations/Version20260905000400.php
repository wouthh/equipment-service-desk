<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align framework-owned index names without changing indexed data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_2dc8224fed442cf4 RENAME TO IDX_B23987FED442CF4');
        $this->addSql('ALTER INDEX messenger_queue_delivery RENAME TO IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_B23987FED442CF4 RENAME TO idx_2dc8224fed442cf4');
        $this->addSql('ALTER INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 RENAME TO messenger_queue_delivery');
    }
}
