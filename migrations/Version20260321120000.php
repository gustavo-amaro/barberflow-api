<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Datas sem atendimento (feriados/folgas) por barbearia.
 */
final class Version20260321120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona closed_dates (JSON) na tabela shop para feriados e dias sem atendimento';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop ADD closed_dates JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop DROP closed_dates');
    }
}
