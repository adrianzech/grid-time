<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add starts_at index for weekend schedule reads';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_race_session_starts_at ON race_session (starts_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_race_session_starts_at');
    }
}
