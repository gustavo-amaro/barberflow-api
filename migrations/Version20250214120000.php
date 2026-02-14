<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250214120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona reminder_sent_at em appointment para controle de lembrete WhatsApp (30 min antes).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment ADD reminder_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment DROP reminder_sent_at');
    }
}
