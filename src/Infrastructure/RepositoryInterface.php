<?php
declare(strict_types=1);

namespace Biblioteca\Infrastructure;

// Contrato comum a todos os repositorios.
// Quem recebe RepositoryInterface nao precisa saber se e de Livro ou de Autor.
interface RepositoryInterface
{
    public function buscar(int $id): ?object;

    public function listar(): array;

    public function salvar(object $entidade): void;

    public function excluir(int $id): void;
}
