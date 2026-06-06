<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create approval_records table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE approval_records (
            id VARCHAR(36) NOT NULL,
            pipeline_run_id VARCHAR(36) NOT NULL,
            job_run_id VARCHAR(36) NOT NULL,
            actor_id VARCHAR(255) NOT NULL,
            approved BOOLEAN NOT NULL,
            decided_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_approval_records_run ON approval_records (pipeline_run_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE approval_records');
    }
}
