<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pipeline_events table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pipeline_events (
            id VARCHAR(36) NOT NULL,
            pipeline_run_id VARCHAR(36) NOT NULL,
            type VARCHAR(64) NOT NULL,
            payload JSON DEFAULT NULL,
            occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_pipeline_events_run ON pipeline_events (pipeline_run_id)');
        $this->addSql('CREATE INDEX IDX_pipeline_events_occurred ON pipeline_events (occurred_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pipeline_events');
    }
}
