<?php
// Demonstracao reproduzivel das 7 evidencias exigidas na avaliacao.
// Rodar a partir da raiz do projeto:   php tests/demo.php
// Cria dados temporarios e apaga tudo no final (pode rodar quantas vezes quiser).
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use Biblioteca\Domain\Autor;
use Biblioteca\Domain\Livro;
use Biblioteca\Infrastructure\AutorRepository;
use Biblioteca\Infrastructure\Conexao;
use Biblioteca\Infrastructure\LivroRepository;
use Biblioteca\Infrastructure\RepositoryInterface;

// ---------------------------------------------------------------
// POLIMORFISMO (R5): recebe QUALQUER repositorio pelo contrato.
// Nao tem if/instanceof pra saber se e Livro ou Autor.
// ---------------------------------------------------------------
function resumoPeloContrato(RepositoryInterface $repo, int $id): string
{
    $total = count($repo->listar());
    $objeto = $repo->buscar($id);
    $encontrado = $objeto === null ? 'nao encontrado' : 'encontrado: ' . $objeto::class;
    return "total de registros = $total | id $id $encontrado";
}

function titulo(string $texto): void
{
    echo PHP_EOL . "=== $texto ===" . PHP_EOL;
}

function confirmar(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException("FALHOU: $mensagem");
    }
    echo "  [OK] $mensagem" . PHP_EOL;
}

function isbnAleatorio(): string
{
    // ISBN unico a cada execucao (a coluna isbn e UNIQUE)
    return '978' . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
}

try {
    $pdo = Conexao::abrir();
    // Mesma conexao nos dois repositorios (necessario para a transacao valer para ambos)
    $livros = new LivroRepository($pdo);
    $autores = new AutorRepository($pdo);

    // -----------------------------------------------------------
    titulo('Evidencia 1 - Cadastro de Livro e Autor (ids gerados pelo MySQL)');
    $autor1 = new Autor('Machado de Assis', 'Brasil');
    $autor2 = new Autor('Clarice Lispector', 'Brasil');
    confirmar($autor1->id() === null, 'antes de salvar, o autor nao tem id');
    $autores->salvar($autor1);
    $autores->salvar($autor2);
    echo "  Autor 1 -> id {$autor1->id()} | Autor 2 -> id {$autor2->id()}" . PHP_EOL;

    $livro = new Livro('Programacao Orientada a Objetos', isbnAleatorio(), 2024);
    $livros->salvar($livro);
    echo "  Livro -> id {$livro->id()} | ISBN {$livro->isbn()}" . PHP_EOL;
    confirmar(is_int($livro->id()) && $livro->id() > 0, 'livro recebeu id AUTO_INCREMENT');

    // -----------------------------------------------------------
    titulo('Evidencia 2 - Busca de Livro por ID e reconstrucao (hidratacao)');
    $recuperado = $livros->buscar($livro->id());
    echo "  Classe: " . $recuperado::class . PHP_EOL;
    echo "  id={$recuperado->id()} titulo=\"{$recuperado->titulo()}\" isbn={$recuperado->isbn()} ano={$recuperado->anoPublicacao()}" . PHP_EOL;
    confirmar($recuperado instanceof Livro, 'linha do MySQL virou objeto Livro');
    confirmar(is_int($recuperado->anoPublicacao()), 'ano_publicacao voltou como int (tipo preservado)');
    confirmar($recuperado !== $livro && $recuperado->id() === $livro->id(), 'objeto novo, mesma identidade (id)');

    // -----------------------------------------------------------
    titulo('Evidencia 3 - Atualizacao e nova consulta');
    $recuperado->alterarTitulo('POO com PHP, PDO e MySQL');
    $recuperado->alterarAnoPublicacao(2026);
    $livros->salvar($recuperado); // tem id -> UPDATE
    $conferido = $livros->buscar($livro->id());
    echo "  Depois do UPDATE: titulo=\"{$conferido->titulo()}\" ano={$conferido->anoPublicacao()}" . PHP_EOL;
    confirmar($conferido->titulo() === 'POO com PHP, PDO e MySQL', 'titulo alterado no banco');
    confirmar($conferido->anoPublicacao() === 2026, 'ano alterado no banco');

    // -----------------------------------------------------------
    titulo('Evidencia 4 - Dois autores no mesmo livro (N:N) e recuperacao da colecao');
    $livros->associarAutor($livro->id(), $autor1->id());
    $livros->associarAutor($livro->id(), $autor2->id());
    $comAutores = $livros->buscarComAutores($livro->id());
    foreach ($comAutores->autores() as $a) {
        echo "  - {$a->nome()} (id {$a->id()}, {$a->nacionalidade()})" . PHP_EOL;
    }
    confirmar(count($comAutores->autores()) === 2, 'colecao reconstruida com 2 autores');
    $comAutores->adicionarAutor($comAutores->autores()[0]);
    confirmar(count($comAutores->autores()) === 2, 'adicionar o mesmo autor de novo nao duplica');
    try {
        $livros->associarAutor($livro->id(), $autor1->id());
        confirmar(false, 'vinculo duplicado deveria ser recusado');
    } catch (PDOException $e) {
        confirmar(true, 'vinculo duplicado recusado pela PK composta (livro_id, autor_id)');
    }

    // -----------------------------------------------------------
    titulo('Evidencia 5 - Polimorfismo: mesmo contrato para os dois repositorios');
    echo '  LivroRepository -> ' . resumoPeloContrato($livros, $livro->id()) . PHP_EOL;
    echo '  AutorRepository -> ' . resumoPeloContrato($autores, $autor1->id()) . PHP_EOL;
    confirmar($livros instanceof RepositoryInterface && $autores instanceof RepositoryInterface,
        'os dois implementam RepositoryInterface');

    // -----------------------------------------------------------
    titulo('Evidencia 6 - Erro intencional em transacao e rollback');
    $autorFantasma = new Autor('Autor que nao existe no banco');
    $autorFantasma->atribuirId(999999); // id proposital que nao existe -> FK vai falhar
    $livroTransacao = new Livro('Livro que nao deve ficar salvo', isbnAleatorio(), 2025);
    try {
        $livros->salvarComAutores($livroTransacao, [$autor1, $autorFantasma]);
        confirmar(false, 'a transacao deveria ter falhado');
    } catch (PDOException $e) {
        echo '  Erro capturado: ' . $e->getMessage() . PHP_EOL;
    }
    $idTentado = $livroTransacao->id();
    echo "  O INSERT chegou a gerar o id $idTentado, mas houve rollBack." . PHP_EOL;
    confirmar($livros->buscar($idTentado) === null, 'o livro NAO ficou no banco (rollback funcionou)');
    confirmar($livroTransacao->autores() === [], 'colecao em memoria nao foi alterada');

    // Caso de sucesso, pra comparar
    $livroOk = new Livro('Livro salvo com autores na transacao', isbnAleatorio(), 2025);
    $livros->salvarComAutores($livroOk, [$autor1, $autor2]);
    confirmar(count($livros->buscarComAutores($livroOk->id())->autores()) === 2,
        'mesma operacao com autores validos: commit com livro + 2 vinculos');

    // -----------------------------------------------------------
    titulo('Evidencia 7 - Exclusao controlada e restricoes de chave estrangeira');
    try {
        $autores->excluir($autor1->id());
        confirmar(false, 'excluir autor vinculado deveria falhar');
    } catch (PDOException $e) {
        echo '  Tentativa de excluir autor com livros: ' . $e->getMessage() . PHP_EOL;
        confirmar(true, 'FK fk_la_autor (ON DELETE RESTRICT) impediu apagar autor vinculado');
    }
    try {
        $livros->excluir($livro->id());
        confirmar(false, 'excluir livro vinculado deveria falhar');
    } catch (PDOException $e) {
        confirmar(true, 'FK fk_la_livro impediu apagar livro que ainda tem autores');
    }
    $livros->excluirComVinculos($livro->id());
    $livros->excluirComVinculos($livroOk->id());
    confirmar($livros->buscar($livro->id()) === null, 'exclusao controlada: vinculos primeiro, depois o livro');
    $autores->excluir($autor1->id());
    $autores->excluir($autor2->id());
    confirmar($autores->buscar($autor1->id()) === null, 'sem vinculos, os autores podem ser excluidos');

    // -----------------------------------------------------------
    titulo('Extra - Validacoes e seguranca');
    try {
        new Livro('   ');
        confirmar(false, 'titulo vazio deveria ser rejeitado');
    } catch (InvalidArgumentException $e) {
        confirmar(true, 'titulo vazio rejeitado pela entidade: ' . $e->getMessage());
    }
    try {
        new Livro('Livro', 'abc');
        confirmar(false, 'ISBN invalido deveria ser rejeitado');
    } catch (InvalidArgumentException $e) {
        confirmar(true, 'ISBN invalido rejeitado: ' . $e->getMessage());
    }
    $textoMalicioso = "Teste'); DROP TABLE livros; --";
    $livroInjecao = new Livro($textoMalicioso);
    $livros->salvar($livroInjecao);
    confirmar($livros->buscar($livroInjecao->id())->titulo() === $textoMalicioso,
        'tentativa de SQL injection gravada como texto comum (prepared statement)');
    $livros->excluir($livroInjecao->id());

    echo PHP_EOL . 'Demonstracao concluida com sucesso.' . PHP_EOL;
} catch (Throwable $e) {
    echo PHP_EOL . 'ERRO: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
