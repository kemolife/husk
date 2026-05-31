<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531154944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add output, started_at, finished_at to job_runs';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE job_runs ADD output TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE job_runs ADD started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE job_runs ADD finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE job_runs DROP output');
        $this->addSql('ALTER TABLE job_runs DROP started_at');
        $this->addSql('ALTER TABLE job_runs DROP finished_at');
    }
}
