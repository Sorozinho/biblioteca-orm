<?php
declare(strict_types=1);

namespace Biblioteca\Infrastructure;

// Cria a conexao PDO. Credenciais vem de variaveis de ambiente
// ou de um arquivo .env local (que NAO vai pro Git - ver .gitignore).
final class Conexao
{
    public static function abrir(): \PDO
    {
        self::carregarEnv(dirname(__DIR__, 2) . '/.env');

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $banco = getenv('DB_NAME') ?: 'biblioteca_orm';
        $usuario = getenv('DB_USER') ?: 'biblioteca';
        $senha = getenv('DB_PASS') ?: '';

        return new \PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,      // erro SQL vira excecao
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,               // prepared statements reais
        ]);
    }

    // Le linhas CHAVE=VALOR do .env. Variavel ja definida no ambiente tem prioridade.
    private static function carregarEnv(string $arquivo): void
    {
        if (!is_file($arquivo)) {
            return;
        }
        foreach (file($arquivo, FILE_IGNORE_NEW_LINES) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
                continue;
            }
            [$chave, $valor] = array_map('trim', explode('=', $linha, 2));
            if (getenv($chave) === false) {
                putenv("$chave=$valor");
            }
        }
    }
}
