<?php
declare(strict_types=1);

namespace Biblioteca\Infrastructure;

use Biblioteca\Domain\Autor;

// Repositorio de Autor: herda o CRUD e so especializa tabela, colunas e conversoes.
final class AutorRepository extends AbstractRepository
{
    protected function tabela(): string
    {
        return 'autores';
    }

    protected function colunas(): array
    {
        return ['nome', 'nacionalidade'];
    }

    protected function hidratar(array $linha): Autor
    {
        $autor = new Autor($linha['nome'], $linha['nacionalidade']);
        $autor->atribuirId((int) $linha['id']);
        return $autor;
    }

    protected function extrair(object $entidade): array
    {
        $autor = $this->comoAutor($entidade);
        return [
            'nome' => $autor->nome(),
            'nacionalidade' => $autor->nacionalidade(),
        ];
    }

    protected function identidade(object $entidade): ?int
    {
        return $this->comoAutor($entidade)->id();
    }

    protected function definirIdentidade(object $entidade, int $id): void
    {
        $this->comoAutor($entidade)->atribuirId($id);
    }

    // Garante que ninguem tente salvar um Livro no repositorio de Autor.
    private function comoAutor(object $entidade): Autor
    {
        if (!$entidade instanceof Autor) {
            throw new \InvalidArgumentException('AutorRepository espera um objeto Autor');
        }
        return $entidade;
    }
}
