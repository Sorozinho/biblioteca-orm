-- BibliotecaORM - modelo relacional (MySQL 8 / InnoDB)
-- Execute uma vez: mysql -u root -p < sql/schema.sql

CREATE DATABASE IF NOT EXISTS biblioteca_orm CHARACTER SET utf8mb4;
USE biblioteca_orm;

-- Entidade principal
CREATE TABLE IF NOT EXISTS livros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    isbn VARCHAR(13) UNIQUE,
    ano_publicacao INT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS autores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    nacionalidade VARCHAR(80) NULL
) ENGINE=InnoDB;

-- Tabela associativa N:N (um livro tem varios autores e um autor tem varios livros).
-- A PK composta impede o mesmo par repetido.
-- ON DELETE RESTRICT: o banco recusa apagar um livro ou autor que ainda tenha vinculo.
CREATE TABLE IF NOT EXISTS livros_autores (
    livro_id INT NOT NULL,
    autor_id INT NOT NULL,
    PRIMARY KEY (livro_id, autor_id),
    CONSTRAINT fk_la_livro FOREIGN KEY (livro_id) REFERENCES livros(id) ON DELETE RESTRICT,
    CONSTRAINT fk_la_autor FOREIGN KEY (autor_id) REFERENCES autores(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Ampliacao (1:N), nao usada pelo codigo nesta versao
CREATE TABLE IF NOT EXISTS exemplares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livro_id INT NOT NULL,
    codigo_patrimonio VARCHAR(30) NOT NULL UNIQUE,
    status ENUM('disponivel','emprestado','manutencao') NOT NULL DEFAULT 'disponivel',
    CONSTRAINT fk_exemplar_livro FOREIGN KEY (livro_id) REFERENCES livros(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
