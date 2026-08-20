<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria planos de assinatura da barbearia para clientes (plan, plan_service, client_subscription, subscription_usage)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plan (id INT AUTO_INCREMENT NOT NULL, shop_id INT NOT NULL, name VARCHAR(100) NOT NULL, price NUMERIC(10, 2) NOT NULL, cycle_days INT DEFAULT 30 NOT NULL, notes LONGTEXT DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_PLAN_SHOP (shop_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE plan_service (id INT AUTO_INCREMENT NOT NULL, plan_id INT NOT NULL, service_id INT NOT NULL, quantity_per_cycle INT NOT NULL, INDEX IDX_PLANSERVICE_PLAN (plan_id), INDEX IDX_PLANSERVICE_SERVICE (service_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client_subscription (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, plan_id INT NOT NULL, status VARCHAR(20) NOT NULL, started_at DATETIME NOT NULL, current_cycle_start DATETIME NOT NULL, current_cycle_end DATETIME NOT NULL, payment_method VARCHAR(30) DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_CLIENTSUB_CLIENT (client_id), INDEX IDX_CLIENTSUB_PLAN (plan_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE subscription_usage (id INT AUTO_INCREMENT NOT NULL, subscription_id INT NOT NULL, service_id INT NOT NULL, appointment_id INT DEFAULT NULL, registered_by_id INT DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_SUBUSAGE_SUBSCRIPTION (subscription_id), INDEX IDX_SUBUSAGE_SERVICE (service_id), INDEX IDX_SUBUSAGE_APPOINTMENT (appointment_id), INDEX IDX_SUBUSAGE_USER (registered_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE plan ADD CONSTRAINT FK_PLAN_SHOP FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_service ADD CONSTRAINT FK_PLANSERVICE_PLAN FOREIGN KEY (plan_id) REFERENCES plan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_service ADD CONSTRAINT FK_PLANSERVICE_SERVICE FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE client_subscription ADD CONSTRAINT FK_CLIENTSUB_CLIENT FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_subscription ADD CONSTRAINT FK_CLIENTSUB_PLAN FOREIGN KEY (plan_id) REFERENCES plan (id)');
        $this->addSql('ALTER TABLE subscription_usage ADD CONSTRAINT FK_SUBUSAGE_SUBSCRIPTION FOREIGN KEY (subscription_id) REFERENCES client_subscription (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscription_usage ADD CONSTRAINT FK_SUBUSAGE_SERVICE FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE subscription_usage ADD CONSTRAINT FK_SUBUSAGE_APPOINTMENT FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE subscription_usage ADD CONSTRAINT FK_SUBUSAGE_USER FOREIGN KEY (registered_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription_usage DROP FOREIGN KEY FK_SUBUSAGE_SUBSCRIPTION');
        $this->addSql('ALTER TABLE subscription_usage DROP FOREIGN KEY FK_SUBUSAGE_SERVICE');
        $this->addSql('ALTER TABLE subscription_usage DROP FOREIGN KEY FK_SUBUSAGE_APPOINTMENT');
        $this->addSql('ALTER TABLE subscription_usage DROP FOREIGN KEY FK_SUBUSAGE_USER');
        $this->addSql('ALTER TABLE plan_service DROP FOREIGN KEY FK_PLANSERVICE_PLAN');
        $this->addSql('ALTER TABLE plan_service DROP FOREIGN KEY FK_PLANSERVICE_SERVICE');
        $this->addSql('ALTER TABLE client_subscription DROP FOREIGN KEY FK_CLIENTSUB_CLIENT');
        $this->addSql('ALTER TABLE client_subscription DROP FOREIGN KEY FK_CLIENTSUB_PLAN');
        $this->addSql('ALTER TABLE plan DROP FOREIGN KEY FK_PLAN_SHOP');

        $this->addSql('DROP TABLE subscription_usage');
        $this->addSql('DROP TABLE client_subscription');
        $this->addSql('DROP TABLE plan_service');
        $this->addSql('DROP TABLE plan');
    }
}
