<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Configuração da barbearia: confirmação automática de agendamentos.
 */
final class Version20260317120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona shop.auto_confirm_appointments para confirmar agendamentos novos automaticamente';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop ADD auto_confirm_appointments TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop DROP auto_confirm_appointments');
    }
}
