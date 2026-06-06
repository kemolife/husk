<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create api_keys table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_keys (
            id VARCHAR(36) NOT NULL,
            hashed_key VARCHAR(64) NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_api_keys_hashed_key ON api_keys (hashed_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_keys');
    }
}
