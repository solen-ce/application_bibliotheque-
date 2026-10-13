<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    echo "Run this script from the command line:\nphp create_user.php firstname lastname email password\n";
    exit(1);
}

[$script, $firstName, $lastName, $email, $password] = array_pad($argv, 5, null);

if (!$firstName || !$lastName || !$email || !$password) {
    echo "Usage: php create_user.php firstname lastname email password\n";
    exit(1);
}

try {
    $db = database();
    $db->beginTransaction();

    // generate unique login
    function generateLoginLocal(PDO $database, string $email, string $firstName, string $lastName): string
    {
        $base = strtolower(trim(($firstName !== '' ? $firstName : '') . '.' . ($lastName !== '' ? $lastName : '')));
        $base = preg_replace('/[^a-z0-9.]/u', '', $base) ?: 'lecteur';
        $base = trim($base, '.');
        if ($base === '') {
            $parts = explode('@', $email);
            $base = preg_replace('/[^a-z0-9]/u', '', $parts[0] ?? '') ?: 'lecteur';
        }

        $login = $base;
        $attempt = 0;
        while (true) {
            $stmt = $database->prepare('SELECT 1 FROM utilisateur WHERE login = :login LIMIT 1');
            $stmt->execute(['login' => $login]);
            if (!$stmt->fetch()) {
                return $login;
            }
            $attempt++;
            $login = $base . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            if ($attempt > 10) {
                return $base . '-' . bin2hex(random_bytes(4));
            }
        }
    }

    $login = generateLoginLocal($db, $email, $firstName, $lastName);

    $stmt = $db->prepare('INSERT INTO utilisateur (login, email, mot_de_passe_hash, role, email_verifie) VALUES (:login, :email, :password_hash, :role, 1)');
    $stmt->execute([
        'login' => $login,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'adherent',
    ]);

    $userId = (int) $db->lastInsertId();

    $stmt = $db->prepare('INSERT INTO adherent (id_utilisateur, numero_carte, nom, prenom, telephone) VALUES (:user_id, :card_number, :last_name, :first_name, :phone)');
    $stmt->execute([
        'user_id' => $userId,
        'card_number' => 'BIB-' . strtoupper(bin2hex(random_bytes(4))),
        'last_name' => $lastName,
        'first_name' => $firstName,
        'phone' => null,
    ]);

    $db->commit();

    echo "Utilisateur créé avec succès:\n";
    echo "ID utilisateur: " . $userId . "\n";
    echo "Login: " . $login . "\n";
    echo "Email: " . $email . "\n";
    exit(0);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Erreur lors de la création de l'utilisateur: " . $e->getMessage() . "\n";
    exit(1);
}
