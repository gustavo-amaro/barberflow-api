<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Intervalo de almoço opcional por barbeiro.
 */
final class Version20260317121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona lunch_start e lunch_end na tabela barber para intervalo de almoço opcional';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE barber ADD lunch_start TIME DEFAULT NULL, ADD lunch_end TIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE barber DROP lunch_start, DROP lunch_end');
    }
}

