<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona membros de equipe autenticáveis, vínculo opcional do dono como barbeiro e recuperação de senha';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD shop_id INT DEFAULT NULL, ADD access_role VARCHAR(20) DEFAULT 'owner' NOT NULL, ADD active TINYINT(1) DEFAULT 1 NOT NULL, ADD must_change_password TINYINT(1) DEFAULT 0 NOT NULL, ADD session_version INT DEFAULT 0 NOT NULL");
        $this->addSql('UPDATE `user` u INNER JOIN shop s ON s.owner_id = u.id SET u.shop_id = s.id');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_USER_SHOP FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_USER_SHOP ON `user` (shop_id)');
        $this->addSql('ALTER TABLE barber ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE barber ADD CONSTRAINT FK_BARBER_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BARBER_USER ON barber (user_id)');
        $this->addSql('CREATE TABLE password_reset_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, INDEX IDX_RESET_USER (user_id), UNIQUE INDEX UNIQ_RESET_HASH (token_hash), INDEX password_reset_token_hash_idx (token_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_RESET_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_token');
        $this->addSql('ALTER TABLE barber DROP FOREIGN KEY FK_BARBER_USER');
        $this->addSql('DROP INDEX UNIQ_BARBER_USER ON barber');
        $this->addSql('ALTER TABLE barber DROP user_id');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_USER_SHOP');
        $this->addSql('DROP INDEX IDX_USER_SHOP ON `user`');
        $this->addSql('ALTER TABLE `user` DROP shop_id, DROP access_role, DROP active, DROP must_change_password, DROP session_version');
    }
}
