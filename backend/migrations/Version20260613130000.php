<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add track timezone offset to race sessions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE race_session ADD track_timezone_offset VARCHAR(6) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE race_session DROP track_timezone_offset');
    }
}
