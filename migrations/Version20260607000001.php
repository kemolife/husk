<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add trigger_context JSON column to pipeline_runs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pipeline_runs ADD trigger_context JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pipeline_runs DROP COLUMN trigger_context');
    }
}
