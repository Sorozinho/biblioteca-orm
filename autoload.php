<?php
// Autoloader proprio (sem Composer).
// Biblioteca\Domain\Livro  ->  src/Domain/Livro.php
declare(strict_types=1);

spl_autoload_register(function (string $classe): void {
    $prefixo = 'Biblioteca\\';
    if (!str_starts_with($classe, $prefixo)) {
        return;
    }
    $relativo = substr($classe, strlen($prefixo));
    $arquivo = __DIR__ . '/src/' . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});
