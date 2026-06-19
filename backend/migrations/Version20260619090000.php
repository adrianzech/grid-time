<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add country names to race events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE race_event ADD country_name VARCHAR(128) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE race_event DROP country_name');
    }
}
