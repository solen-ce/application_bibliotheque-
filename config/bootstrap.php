<?php
declare(strict_types=1);

use Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function envRequired(string $name): string
{
    $value = $_ENV[$name] ?? '';
    if ($value === '') {
        throw new RuntimeException("La variable d'environnement {$name} est manquante.");
    }

    return $value;
}

function database(): PDO
{
    $host = envRequired('DB_HOST');
    $port = $_ENV['DB_PORT'] ?? '3306';
    $database = envRequired('DB_NAME');
    $username = envRequired('DB_USER');
    $password = $_ENV['DB_PASSWORD'] ?? '';

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
}
