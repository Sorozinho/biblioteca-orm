<?php
declare(strict_types=1);

namespace Biblioteca\Domain;

// Entidade Autor: so guarda dados e regras. Nao conhece SQL nem PDO.
final class Autor
{
    private ?int $id = null;          // null = ainda nao foi salvo no banco
    private string $nome;
    private ?string $nacionalidade;

    public function __construct(string $nome, ?string $nacionalidade = null)
    {
        $this->alterarNome($nome);
        $this->alterarNacionalidade($nacionalidade);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    // O id so pode ser definido uma vez (pelo repositorio, apos INSERT ou na hidratacao).
    public function atribuirId(int $id): void
    {
        if ($id <= 0 || ($this->id !== null && $this->id !== $id)) {
            throw new \LogicException('Identidade invalida para Autor');
        }
        $this->id = $id;
    }

    public function nome(): string
    {
        return $this->nome;
    }

    public function nacionalidade(): ?string
    {
        return $this->nacionalidade;
    }

    public function alterarNome(string $nome): void
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new \InvalidArgumentException('Nome do autor e obrigatorio');
        }
        if (mb_strlen($nome) > 150) {
            throw new \InvalidArgumentException('Nome do autor tem no maximo 150 caracteres');
        }
        $this->nome = $nome;
    }

    public function alterarNacionalidade(?string $nacionalidade): void
    {
        $nacionalidade = $nacionalidade === null ? null : trim($nacionalidade);
        if ($nacionalidade === '') {
            $nacionalidade = null;
        }
        if ($nacionalidade !== null && mb_strlen($nacionalidade) > 80) {
            throw new \InvalidArgumentException('Nacionalidade tem no maximo 80 caracteres');
        }
        $this->nacionalidade = $nacionalidade;
    }
}
