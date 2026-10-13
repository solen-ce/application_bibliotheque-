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

function ensureDatabaseSchema(PDO $database): void
{
    $database->exec(
        "CREATE TABLE IF NOT EXISTS utilisateur (
            id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            mot_de_passe_hash VARCHAR(255) NOT NULL,
            role ENUM('bibliothecaire', 'adherent') NOT NULL DEFAULT 'adherent',
            email_verifie TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB"
    );

    $database->exec(
        "CREATE TABLE IF NOT EXISTS adherent (
            id_adherent INT AUTO_INCREMENT PRIMARY KEY,
            id_utilisateur INT NOT NULL UNIQUE,
            numero_carte VARCHAR(20) NOT NULL UNIQUE,
            nom VARCHAR(50) NOT NULL,
            prenom VARCHAR(50) NOT NULL,
            telephone VARCHAR(20),
            statut ENUM('actif', 'suspendu') NOT NULL DEFAULT 'actif',
            date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE
        ) ENGINE=InnoDB"
    );

    $database->exec(
        "CREATE TABLE IF NOT EXISTS code_verification_email (
            id_utilisateur INT NOT NULL PRIMARY KEY,
            code_hash VARCHAR(255) NOT NULL,
            expire_le DATETIME NOT NULL,
            tentatives TINYINT UNSIGNED NOT NULL DEFAULT 0,
            envoye_le DATETIME NOT NULL,
            CONSTRAINT fk_code_verification_utilisateur
                FOREIGN KEY (id_utilisateur)
                REFERENCES utilisateur(id_utilisateur)
                ON DELETE CASCADE
        ) ENGINE=InnoDB"
    );

    $database->exec(
        "CREATE TABLE IF NOT EXISTS parametre (
            cle VARCHAR(50) PRIMARY KEY,
            valeur VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB"
    );

    $database->exec(
        "INSERT INTO parametre (cle, valeur)
        SELECT 'duree_pret_jours', '14'
        WHERE NOT EXISTS (SELECT 1 FROM parametre WHERE cle = 'duree_pret_jours')"
    );
    $database->exec(
        "INSERT INTO parametre (cle, valeur)
        SELECT 'max_emprunts', '3'
        WHERE NOT EXISTS (SELECT 1 FROM parametre WHERE cle = 'max_emprunts')"
    );
    $database->exec(
        "INSERT INTO parametre (cle, valeur)
        SELECT 'penalite_par_jour', '100'
        WHERE NOT EXISTS (SELECT 1 FROM parametre WHERE cle = 'penalite_par_jour')"
    );

    $columns = $database->query("SHOW COLUMNS FROM utilisateur LIKE 'email_verifie'")->fetchAll();
    if ($columns === []) {
        $database->exec("ALTER TABLE utilisateur ADD COLUMN email_verifie TINYINT(1) NOT NULL DEFAULT 0");
    }
}

function database(): PDO
{
    $host = envRequired('DB_HOST');
    $port = $_ENV['DB_PORT'] ?? '3306';
    $databaseName = envRequired('DB_NAME');
    $username = envRequired('DB_USER');
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $database = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );

    $database->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $database->exec("USE `{$databaseName}`");
    ensureDatabaseSchema($database);

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
}
