-- Banco usado pela Evolution API (WhatsApp)
-- Roda como root no primeiro start do container
GRANT ALL PRIVILEGES ON evolution.* TO 'barberflow'@'%';
FLUSH PRIVILEGES;
CREATE DATABASE IF NOT EXISTS evolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

