<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add schedule update marker to seasons';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE season ADD schedule_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE season DROP schedule_updated_at');
    }
}
