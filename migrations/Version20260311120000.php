<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Permite armazenar imagens (base64) em logo da barbearia, avatar do barbeiro,
 * imagem do produto e adiciona imagem no serviço.
 */
final class Version20260311120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Colunas para imagens: shop.logo, barber.avatar, product.image como LONGTEXT; service.image nova coluna LONGTEXT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop MODIFY logo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE barber MODIFY avatar LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE product MODIFY image LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD image LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop MODIFY logo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE barber MODIFY avatar VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE product MODIFY image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service DROP image');
    }
}
