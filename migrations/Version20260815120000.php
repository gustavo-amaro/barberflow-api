<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona cor de destaque da marca (accent_color) na barbearia';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop ADD accent_color VARCHAR(7) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop DROP accent_color');
    }
}
