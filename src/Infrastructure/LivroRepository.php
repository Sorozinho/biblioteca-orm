<?php
declare(strict_types=1);

namespace Biblioteca\Infrastructure;

use Biblioteca\Domain\Autor;
use Biblioteca\Domain\Livro;

// Repositorio de Livro: herda o CRUD e adiciona o relacionamento N:N com autores.
final class LivroRepository extends AbstractRepository
{
    protected function tabela(): string
    {
        return 'livros';
    }

    protected function colunas(): array
    {
        return ['titulo', 'isbn', 'ano_publicacao'];
    }

    protected function hidratar(array $linha): Livro
    {
        $ano = $linha['ano_publicacao'] === null ? null : (int) $linha['ano_publicacao'];
        $livro = new Livro($linha['titulo'], $linha['isbn'], $ano);
        $livro->atribuirId((int) $linha['id']);
        return $livro;
    }

    protected function extrair(object $entidade): array
    {
        $livro = $this->comoLivro($entidade);
        return [
            'titulo' => $livro->titulo(),
            'isbn' => $livro->isbn(),
            'ano_publicacao' => $livro->anoPublicacao(),
        ];
    }

    protected function identidade(object $entidade): ?int
    {
        return $this->comoLivro($entidade)->id();
    }

    protected function definirIdentidade(object $entidade, int $id): void
    {
        $this->comoLivro($entidade)->atribuirId($id);
    }

    // ---------------- Relacionamento N:N ----------------

    // Grava um vinculo na tabela livros_autores.
    public function associarAutor(int $livroId, int $autorId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO livros_autores (livro_id, autor_id) VALUES (:livro, :autor)'
        );
        $stmt->execute(['livro' => $livroId, 'autor' => $autorId]);
    }

    // Salva o livro E os vinculos com autores numa unica transacao.
    // Se um vinculo falhar (ex.: autor inexistente), o livro tambem nao fica no banco.
    /** @param Autor[] $autores */
    public function salvarComAutores(Livro $livro, array $autores): void
    {
        $this->emTransacao(function () use ($livro, $autores): void {
            $this->salvar($livro);
            foreach ($autores as $autor) {
                if ($autor->id() === null) {
                    throw new \InvalidArgumentException('Salve o autor antes de vincular');
                }
                $this->associarAutor($livro->id(), $autor->id());
            }
        });
        // So mexe na colecao em memoria depois do commit.
        foreach ($autores as $autor) {
            $livro->adicionarAutor($autor);
        }
    }

    // Busca o livro ja com a colecao de autores montada (uma consulta so, com JOIN).
    // O JOIN devolve uma linha por autor; o array $autoresCarregados garante
    // que cada autor vire UM objeto so, mesmo que apareca em mais de uma linha.
    public function buscarComAutores(int $id): ?Livro
    {
        $sql = 'SELECT l.*, a.id AS autor_id, a.nome AS autor_nome,
                       a.nacionalidade AS autor_nacionalidade
                FROM livros l
                LEFT JOIN livros_autores la ON la.livro_id = l.id
                LEFT JOIN autores a ON a.id = la.autor_id
                WHERE l.id = :id
                ORDER BY a.nome';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $linhas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if ($linhas === []) {
            return null;
        }

        $livro = $this->hidratar($linhas[0]);
        $autoresCarregados = [];
        foreach ($linhas as $linha) {
            if ($linha['autor_id'] === null) {
                continue; // livro sem autor: LEFT JOIN traz autor_id nulo
            }
            $autorId = (int) $linha['autor_id'];
            if (!isset($autoresCarregados[$autorId])) {
                $autor = new Autor($linha['autor_nome'], $linha['autor_nacionalidade']);
                $autor->atribuirId($autorId);
                $autoresCarregados[$autorId] = $autor;
            }
            $livro->adicionarAutor($autoresCarregados[$autorId]);
        }
        return $livro;
    }

    // Exclusao controlada: primeiro apaga os vinculos, depois o livro, tudo na mesma transacao.
    // (Um excluir() direto falha se o livro ainda tiver autores, por causa da FK.)
    public function excluirComVinculos(int $id): void
    {
        $this->emTransacao(function () use ($id): void {
            $stmt = $this->pdo->prepare('DELETE FROM livros_autores WHERE livro_id = :id');
            $stmt->execute(['id' => $id]);
            $this->excluir($id);
        });
    }

    private function comoLivro(object $entidade): Livro
    {
        if (!$entidade instanceof Livro) {
            throw new \InvalidArgumentException('LivroRepository espera um objeto Livro');
        }
        return $entidade;
    }
}
