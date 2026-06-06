<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add matrix_values column to job_runs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_runs ADD matrix_values JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_runs DROP COLUMN matrix_values');
    }
}
