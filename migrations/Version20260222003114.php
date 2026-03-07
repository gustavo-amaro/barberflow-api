<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222003114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subscription_charge (id INT AUTO_INCREMENT NOT NULL, plan VARCHAR(20) NOT NULL, amount NUMERIC(10, 2) NOT NULL, gateway VARCHAR(30) NOT NULL, gateway_payment_id VARCHAR(100) DEFAULT NULL, status VARCHAR(20) NOT NULL, payment_data JSON DEFAULT NULL, created_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, user_id INT NOT NULL, shop_id INT NOT NULL, INDEX IDX_9C9DBCB3A76ED395 (user_id), INDEX IDX_9C9DBCB34D16C4DD (shop_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE subscription_charge ADD CONSTRAINT FK_9C9DBCB3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscription_charge ADD CONSTRAINT FK_9C9DBCB34D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop ADD subscription_plan VARCHAR(20) DEFAULT NULL, ADD subscription_ends_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription_charge DROP FOREIGN KEY FK_9C9DBCB3A76ED395');
        $this->addSql('ALTER TABLE subscription_charge DROP FOREIGN KEY FK_9C9DBCB34D16C4DD');
        $this->addSql('DROP TABLE subscription_charge');
        $this->addSql('ALTER TABLE shop DROP subscription_plan, DROP subscription_ends_at');
    }
}
