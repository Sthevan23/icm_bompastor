-- Importe no phpMyAdmin da Hostinger (aba SQL ou Importar)
-- Banco: crie antes em MySQL Databases (ex.: u123456_icm)

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS escalas (
  id CHAR(16) NOT NULL,
  igreja VARCHAR(39) NOT NULL,
  motivo VARCHAR(39) NOT NULL,
  date0 DATE NOT NULL,
  date1 DATE NOT NULL,
  owner_token CHAR(64) NOT NULL,
  current_list JSON NOT NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_atualizado (atualizado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
