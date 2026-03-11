<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216205145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        //$this->addSql('CREATE TABLE shop_schedule (id INT AUTO_INCREMENT NOT NULL, day_of_week SMALLINT UNSIGNED NOT NULL, is_open TINYINT DEFAULT 1 NOT NULL, time_open TIME DEFAULT NULL, time_close TIME DEFAULT NULL, shop_id INT NOT NULL, INDEX IDX_8180EB6D4D16C4DD (shop_id), UNIQUE INDEX shop_day_unique (shop_id, day_of_week), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        //$this->addSql('ALTER TABLE shop_schedule ADD CONSTRAINT FK_8180EB6D4D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shop_schedule DROP FOREIGN KEY FK_8180EB6D4D16C4DD');
        $this->addSql('DROP TABLE shop_schedule');
    }
}
