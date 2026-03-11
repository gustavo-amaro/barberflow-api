<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Armazena apenas caminho das imagens (disco): logo, avatar, image como VARCHAR(255).
 */
final class Version20260311130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Colunas de imagem como path: shop.logo, barber.avatar, product.image, service.image VARCHAR(255)';
    }

    public function up(Schema $schema): void
    {
        // Remove dados base64 antigos (agora usamos path em disco)
        $this->addSql('ALTER TABLE shop MODIFY logo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE barber MODIFY avatar VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE product MODIFY image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service MODIFY image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop MODIFY logo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE barber MODIFY avatar LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE product MODIFY image LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE service MODIFY image LONGTEXT DEFAULT NULL');
    }
}
