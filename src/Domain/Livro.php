<?php
declare(strict_types=1);

namespace Biblioteca\Domain;

// Entidade Livro: dados, validacoes e a colecao de autores (lado objeto do N:N).
// No banco essa mesma relacao fica na tabela livros_autores.
final class Livro
{
    private ?int $id = null;
    private string $titulo;
    private ?string $isbn;
    private ?int $anoPublicacao;

    /** @var Autor[] */
    private array $autores = [];

    public function __construct(string $titulo, ?string $isbn = null, ?int $anoPublicacao = null)
    {
        $this->alterarTitulo($titulo);
        $this->alterarIsbn($isbn);
        $this->alterarAnoPublicacao($anoPublicacao);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function atribuirId(int $id): void
    {
        if ($id <= 0 || ($this->id !== null && $this->id !== $id)) {
            throw new \LogicException('Identidade invalida para Livro');
        }
        $this->id = $id;
    }

    public function titulo(): string
    {
        return $this->titulo;
    }

    public function isbn(): ?string
    {
        return $this->isbn;
    }

    public function anoPublicacao(): ?int
    {
        return $this->anoPublicacao;
    }

    public function alterarTitulo(string $titulo): void
    {
        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new \InvalidArgumentException('Titulo e obrigatorio');
        }
        if (mb_strlen($titulo) > 200) {
            throw new \InvalidArgumentException('Titulo tem no maximo 200 caracteres');
        }
        $this->titulo = $titulo;
    }

    // ISBN opcional; se vier, aceita 10 ou 13 digitos (tracos e espacos sao removidos).
    public function alterarIsbn(?string $isbn): void
    {
        if ($isbn === null || trim($isbn) === '') {
            $this->isbn = null;
            return;
        }
        $limpo = str_replace(['-', ' '], '', $isbn);
        if (!preg_match('/^(\d{13}|\d{9}[\dX])$/', $limpo)) {
            throw new \InvalidArgumentException('ISBN deve ter 10 ou 13 digitos');
        }
        $this->isbn = $limpo;
    }

    public function alterarAnoPublicacao(?int $ano): void
    {
        $anoMaximo = (int) date('Y') + 1;
        if ($ano !== null && ($ano < 1450 || $ano > $anoMaximo)) {
            throw new \InvalidArgumentException("Ano de publicacao deve estar entre 1450 e $anoMaximo");
        }
        $this->anoPublicacao = $ano;
    }

    // Nao deixa o mesmo autor entrar duas vezes (mesmo objeto ou mesmo id).
    public function adicionarAutor(Autor $autor): void
    {
        foreach ($this->autores as $existente) {
            if ($existente === $autor) {
                return;
            }
            if ($autor->id() !== null && $existente->id() === $autor->id()) {
                return;
            }
        }
        $this->autores[] = $autor;
    }

    /** @return Autor[] */
    public function autores(): array
    {
        return $this->autores;
    }
}
