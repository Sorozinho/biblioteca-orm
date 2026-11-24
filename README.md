# BibliotecaORM

Mini-ORM didático em **PHP puro + PDO + MySQL** (padrão Data Mapper simplificado), sem nenhum ORM de terceiros.

**Aluno:** Lucas Alves Scortegagna — código 427420
**Disciplina:** Programação II — Ciência da Computação — UNOESC Videira
**Professor:** Leandro Otavio Cordova Vieira

## Requisitos

- PHP 8.2 ou superior com a extensão `pdo_mysql` habilitada
  (no Windows: no `php.ini`, deixar a linha `extension=pdo_mysql` sem `;` na frente)
- MySQL 8 (InnoDB)
- Não precisa de Composer

## Como rodar

1. **Criar o banco** (cria `biblioteca_orm` e as tabelas):
   ```bash
   mysql -u root -p < sql/schema.sql
   ```
2. **(Recomendado) Criar um usuário só para o projeto:**
   ```sql
   CREATE USER 'biblioteca'@'localhost' IDENTIFIED BY 'sua_senha';
   GRANT ALL ON biblioteca_orm.* TO 'biblioteca'@'localhost';
   ```
3. **Configurar a conexão:** copie `.env.example` para `.env` e preencha usuário e senha.
   O `.env` está no `.gitignore`, então a senha não vai para o repositório.
   (Também funciona com variáveis de ambiente `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.)
4. **Rodar a demonstração**, a partir da pasta do projeto:
   ```bash
   php tests/demo.php
   ```
   O script executa as 7 evidências pedidas na avaliação, confere cada uma (`[OK]`) e apaga os
   dados temporários no final, então pode ser executado quantas vezes quiser.

## Estrutura

```
sql/schema.sql                         tabelas livros, autores, livros_autores (+ exemplares)
autoload.php                           autoloader próprio (spl_autoload_register)
src/Domain/Livro.php                   entidade (propriedades privadas, validações, coleção de autores)
src/Domain/Autor.php                   entidade
src/Infrastructure/RepositoryInterface.php   contrato: buscar, listar, salvar, excluir
src/Infrastructure/AbstractRepository.php    CRUD genérico, transação, proteção de identificadores
src/Infrastructure/LivroRepository.php       mapeamento de Livro + relacionamento N:N
src/Infrastructure/AutorRepository.php       mapeamento de Autor
src/Infrastructure/Conexao.php               conexão PDO (lê .env)
tests/demo.php                         demonstração reproduzível das evidências
```

## Onde está cada requisito

| Requisito | Onde |
|---|---|
| R1 Modelo relacional (PK, FK) | `sql/schema.sql` |
| R2 Entidades encapsuladas | `src/Domain/Livro.php`, `src/Domain/Autor.php` — `id` só pode ser atribuído uma vez |
| R3 Contrato | `RepositoryInterface` |
| R4 Herança e abstração | `AbstractRepository` (buscar, listar, salvar, excluir, emTransacao) estendida por `LivroRepository` e `AutorRepository` |
| R5 Polimorfismo | função `resumoPeloContrato(RepositoryInterface $repo, ...)` em `tests/demo.php` |
| R6 CRUD | Livro: INSERT, SELECT por id, listar, UPDATE, DELETE. Autor: os mesmos (herdados) |
| R7 Hidratação | métodos `hidratar()` dos repositórios (converte tipos, define id) |
| R8 N:N | `associarAutor()`, `buscarComAutores()` (um JOIN, sem duplicar objetos Autor) |
| R9 Segurança e transação | prepared statements em todo SQL; `identificadorSeguro()`; `salvarComAutores()` e `excluirComVinculos()` com rollback |
| R10 Organização | esta estrutura, este README, `schema.sql`, `tests/demo.php`, `.env` fora do Git |

## Limitações conhecidas

- Só funciona com tabelas que têm chave primária inteira chamada `id`.
- Relacionamentos não carregam sozinhos: é preciso chamar `buscarComAutores()`.
- Sem identity map global, controle de concorrência, migrations ou query builder.
- Se a transação sofre rollback, o objeto `Livro` em memória continua com o id que o INSERT chegou a gerar
  (o registro não existe no banco). O código que chamou deve descartar esse objeto.
- `salvar()` decide entre INSERT e UPDATE só pela existência do id; não detecta registro apagado por fora.
- A tabela `exemplares` existe no banco mas não tem repositório nesta versão.
