<?php
declare(strict_types=1);

namespace Biblioteca\Infrastructure;

// Classe abstrata: tem o CRUD que vale pra qualquer tabela.
// As filhas (LivroRepository, AutorRepository) so dizem:
//   - qual tabela e quais colunas
//   - como transformar linha em objeto (hidratar) e objeto em array (extrair)
abstract class AbstractRepository implements RepositoryInterface
{
    public function __construct(protected \PDO $pdo)
    {
    }

    // ---- O que cada repositorio concreto precisa informar ----
    abstract protected function tabela(): string;

    /** @return string[] colunas gravaveis (sem o id) */
    abstract protected function colunas(): array;

    // linha do banco -> objeto
    abstract protected function hidratar(array $linha): object;

    // objeto -> array coluna => valor
    abstract protected function extrair(object $entidade): array;

    abstract protected function identidade(object $entidade): ?int;

    abstract protected function definirIdentidade(object $entidade, int $id): void;

    // ---- Comportamentos compartilhados ----

    public function buscar(int $id): ?object
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->tabelaSegura() . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $linha === false ? null : $this->hidratar($linha);
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM ' . $this->tabelaSegura() . ' ORDER BY id');
        $objetos = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $linha) {
            $objetos[] = $this->hidratar($linha);
        }
        return $objetos;
    }

    // Sem id -> INSERT (e pega o id gerado pelo MySQL). Com id -> UPDATE.
    public function salvar(object $entidade): void
    {
        $dados = $this->extrair($entidade);
        $colunas = $this->colunasSeguras();
        if (array_keys($dados) !== $colunas) {
            throw new \LogicException('Mapeamento inconsistente entre extrair() e colunas()');
        }

        $id = $this->identidade($entidade);
        if ($id === null) {
            $nomes = implode(', ', $colunas);
            $marcadores = implode(', ', array_map(fn(string $c) => ':' . $c, $colunas));
            $sql = 'INSERT INTO ' . $this->tabelaSegura() . " ($nomes) VALUES ($marcadores)";
            $this->pdo->prepare($sql)->execute($dados);
            $this->definirIdentidade($entidade, (int) $this->pdo->lastInsertId());
        } else {
            $atribuicoes = implode(', ', array_map(fn(string $c) => "$c = :$c", $colunas));
            $sql = 'UPDATE ' . $this->tabelaSegura() . " SET $atribuicoes WHERE id = :id";
            $this->pdo->prepare($sql)->execute($dados + ['id' => $id]);
        }
    }

    public function excluir(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . $this->tabelaSegura() . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    // Executa um bloco inteiro dentro de uma transacao.
    // Deu erro em qualquer passo -> rollBack (nada fica gravado) e a excecao sobe.
    protected function emTransacao(callable $operacao): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $resultado = $operacao();
            $this->pdo->commit();
            return $resultado;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // PDO nao deixa usar ? ou :param para nome de tabela/coluna.
    // Por isso esses nomes vem SO do codigo e ainda sao conferidos aqui.
    protected function identificadorSeguro(string $nome): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $nome)) {
            throw new \LogicException("Identificador SQL nao permitido: $nome");
        }
        return $nome;
    }

    private function tabelaSegura(): string
    {
        return $this->identificadorSeguro($this->tabela());
    }

    private function colunasSeguras(): array
    {
        return array_map(fn(string $c) => $this->identificadorSeguro($c), $this->colunas());
    }
}
