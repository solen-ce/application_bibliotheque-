<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/config/bootstrap.php';

$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $https,
    'samesite' => 'Lax',
]);
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirectTo(string $view): never
{
    header('Location: index.php?view=' . rawurlencode($view), true, 303);
    exit;
}

function requireValidCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        setFlash('error', 'Votre session a expiré. Veuillez réessayer.');
        redirectTo('login');
    }
}

function sendVerificationCode(string $email, string $firstName, string $code): void
{
    $port = filter_var($_ENV['SMTP_PORT'] ?? '587', FILTER_VALIDATE_INT);
    if ($port === false || $port < 1 || $port > 65535) {
        throw new RuntimeException('Le port SMTP configuré est invalide.');
    }

    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = envRequired('SMTP_HOST');
    $mailer->SMTPAuth = true;
    $mailer->Username = envRequired('SMTP_USERNAME');
    $mailer->Password = envRequired('SMTP_PASSWORD');
    $mailer->Port = $port;
    $mailer->CharSet = PHPMailer::CHARSET_UTF8;

    $secure = strtolower($_ENV['SMTP_SECURE'] ?? 'tls');
    if ($secure === 'tls') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($secure === 'ssl') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        throw new RuntimeException('SMTP_SECURE doit valoir tls ou ssl.');
    }

    $mailer->setFrom(envRequired('MAIL_FROM_ADDRESS'), $_ENV['MAIL_FROM_NAME'] ?? 'La bibliothèque');
    $mailer->addAddress($email, $firstName);
    $mailer->Subject = 'Votre code de vérification';
    $mailer->Body = "Bonjour {$firstName},\n\n"
        . "Votre code de vérification est : {$code}\n"
        . "Il est valable pendant 10 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.";
    $mailer->send();
}

function sendPasswordResetCode(string $email, string $firstName, string $code): void
{
    $port = filter_var($_ENV['SMTP_PORT'] ?? '587', FILTER_VALIDATE_INT);
    if ($port === false || $port < 1 || $port > 65535) {
        throw new RuntimeException('Le port SMTP configuré est invalide.');
    }

    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = envRequired('SMTP_HOST');
    $mailer->SMTPAuth = true;
    $mailer->Username = envRequired('SMTP_USERNAME');
    $mailer->Password = envRequired('SMTP_PASSWORD');
    $mailer->Port = $port;
    $mailer->CharSet = PHPMailer::CHARSET_UTF8;

    $secure = strtolower($_ENV['SMTP_SECURE'] ?? 'tls');
    if ($secure === 'tls') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($secure === 'ssl') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        throw new RuntimeException('SMTP_SECURE doit valoir tls ou ssl.');
    }

    $mailer->setFrom(envRequired('MAIL_FROM_ADDRESS'), $_ENV['MAIL_FROM_NAME'] ?? 'La bibliothèque');
    $mailer->addAddress($email, $firstName);
    $mailer->Subject = 'Réinitialisation du mot de passe';
    $mailer->Body = "Bonjour {$firstName},\n\n"
        . "Vous avez demandé une réinitialisation de votre mot de passe.\n"
        . "Votre code de vérification est : {$code}\n"
        . "Il est valable pendant 10 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.";
    $mailer->send();
}

function newVerificationCode(): string
{
    return str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
}

function storeVerificationCode(PDO $database, int $userId, string $code): void
{
    $statement = $database->prepare(
        'DELETE FROM code_verification_email WHERE id_utilisateur = :user_id'
    );
    $statement->execute(['user_id' => $userId]);

    $statement = $database->prepare(
        'INSERT INTO code_verification_email '
        . '(id_utilisateur, code_hash, expire_le, tentatives, envoye_le) '
        . 'VALUES (:user_id, :code_hash, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, NOW())'
    );
    $statement->execute([
        'user_id' => $userId,
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
    ]);
}

function generateLogin(PDO $database, string $email, string $firstName, string $lastName): string
{
    $base = strtolower(trim(($firstName !== '' ? $firstName : '') . '.' . ($lastName !== '' ? $lastName : '')));
    $base = preg_replace('/[^a-z0-9.]/u', '', $base) ?? 'lecteur';
    $base = trim($base, '.');

    if ($base === '' || $base === '.') {
        $parts = explode('@', $email);
        $base = preg_replace('/[^a-z0-9]/u', '', $parts[0] ?? 'lecteur') ?? 'lecteur';
    }

    $login = $base === '' ? 'lecteur' : $base;
    $attempt = 0;

    while (true) {
        $statement = $database->prepare('SELECT 1 FROM utilisateur WHERE login = :login LIMIT 1');
        $statement->execute(['login' => $login]);
        if (!$statement->fetch()) {
            return $login;
        }

        $attempt++;
        $login = $base . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        if ($attempt > 10) {
            $login = $base . '-' . bin2hex(random_bytes(4));
            return $login;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';

    try {
        $database = database();

        if ($action === 'login') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $statement = $database->prepare(
                'SELECT id_utilisateur, login, mot_de_passe_hash, role, email_verifie '
                . 'FROM utilisateur WHERE email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (!$user || !password_verify($password, $user['mot_de_passe_hash'])) {
                setFlash('error', 'Adresse e-mail ou mot de passe incorrect.');
                redirectTo('login');
            }

            if ((int) $user['email_verifie'] !== 1) {
                $_SESSION['pending_email'] = $email;
                setFlash('error', 'Confirmez votre adresse e-mail avec le code reçu.');
                redirectTo('verify');
            }

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['id_utilisateur'],
                'login' => $user['login'],
                'role' => $user['role'],
            ];
            setFlash('success', 'Vous êtes connecté(e).');
            redirectTo('account');
        }

        if ($action === 'register') {
            $firstName = trim((string) ($_POST['prenom'] ?? ''));
            $lastName = trim((string) ($_POST['nom'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['telephone'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

            $values = [
                'prenom' => $firstName,
                'nom' => $lastName,
                'email' => $email,
                'telephone' => $phone,
            ];

            if (
                mb_strlen($firstName) < 1 || mb_strlen($firstName) > 50
                || mb_strlen($lastName) < 1 || mb_strlen($lastName) > 50
                || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100
                || mb_strlen($phone) > 20
            ) {
                $_SESSION['form_values'] = $values;
                setFlash('error', 'Vérifiez les informations saisies puis réessayez.');
                redirectTo('register');
            }

            if (mb_strlen($password) < 10 || $password !== $passwordConfirmation) {
                $_SESSION['form_values'] = $values;
                setFlash(
                    'error',
                    $password !== $passwordConfirmation
                        ? 'Les deux mots de passe ne correspondent pas.'
                        : 'Le mot de passe doit contenir au moins 10 caractères.',
                );
                redirectTo('register');
            }

            $database->beginTransaction();
            $login = generateLogin($database, $email, $firstName, $lastName);

            $statement = $database->prepare(
                'INSERT INTO utilisateur (login, email, mot_de_passe_hash, role, email_verifie) '
                . 'VALUES (:login, :email, :password_hash, :role, 0)'
            );
            $statement->execute([
                'login' => $login,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'adherent',
            ]);
            $userId = (int) $database->lastInsertId();

            $statement = $database->prepare(
                'INSERT INTO adherent (id_utilisateur, numero_carte, nom, prenom, telephone) '
                . 'VALUES (:user_id, :card_number, :last_name, :first_name, :phone)'
            );
            $statement->execute([
                'user_id' => $userId,
                'card_number' => 'BIB-' . strtoupper(bin2hex(random_bytes(4))),
                'last_name' => $lastName,
                'first_name' => $firstName,
                'phone' => $phone === '' ? null : $phone,
            ]);

            $code = newVerificationCode();
            storeVerificationCode($database, $userId, $code);
            $database->commit();

            $_SESSION['pending_email'] = $email;
            try {
                sendVerificationCode($email, $firstName, $code);
                setFlash('success', 'Votre compte est créé. Saisissez le code envoyé à votre adresse e-mail.');
            } catch (MailException | RuntimeException $exception) {
                error_log('Échec de l’envoi du code de vérification : ' . $exception->getMessage());
                setFlash(
                    'error',
                    'Votre compte est créé, mais le courriel n’a pas pu être envoyé. '
                    . 'Vérifiez la configuration SMTP puis demandez un nouveau code.',
                );
            }
            redirectTo('verify');
        }

        if ($action === 'verify') {
            $email = (string) ($_SESSION['pending_email'] ?? '');
            $code = trim((string) ($_POST['code'] ?? ''));
            if ($email === '' || !preg_match('/^\d{8}$/', $code)) {
                setFlash('error', 'Saisissez le code à 8 chiffres reçu par e-mail.');
                redirectTo('verify');
            }

            $database->beginTransaction();
            $statement = $database->prepare(
                'SELECT u.id_utilisateur, v.code_hash, v.tentatives, '
                . '(v.expire_le > NOW()) AS code_valide '
                . 'FROM utilisateur u '
                . 'JOIN code_verification_email v ON v.id_utilisateur = u.id_utilisateur '
                . 'WHERE u.email = :email AND u.email_verifie = 0 LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['email' => $email]);
            $verification = $statement->fetch();

            if (
                !$verification
                || (int) $verification['tentatives'] >= 5
                || (int) $verification['code_valide'] !== 1
            ) {
                $database->rollBack();
                setFlash('error', 'Le code est invalide ou expiré. Demandez un nouveau code.');
                redirectTo('verify');
            }

            $statement = $database->prepare(
                'UPDATE code_verification_email SET tentatives = tentatives + 1 '
                . 'WHERE id_utilisateur = :user_id'
            );
            $statement->execute(['user_id' => $verification['id_utilisateur']]);

            if (!password_verify($code, $verification['code_hash'])) {
                $database->commit();
                setFlash('error', 'Le code saisi est incorrect.');
                redirectTo('verify');
            }

            $statement = $database->prepare(
                'UPDATE utilisateur SET email_verifie = 1 WHERE id_utilisateur = :user_id'
            );
            $statement->execute(['user_id' => $verification['id_utilisateur']]);
            $statement = $database->prepare(
                'DELETE FROM code_verification_email WHERE id_utilisateur = :user_id'
            );
            $statement->execute(['user_id' => $verification['id_utilisateur']]);
            $database->commit();

            unset($_SESSION['pending_email']);
            setFlash('success', 'Votre adresse e-mail est confirmée. Vous pouvez vous connecter.');
            redirectTo('login');
        }

        if ($action === 'resend') {
            $email = (string) ($_SESSION['pending_email'] ?? '');
            if ($email === '') {
                setFlash('error', 'Aucune inscription en attente de vérification.');
                redirectTo('register');
            }

            $statement = $database->prepare(
                'SELECT u.id_utilisateur, a.prenom, '
                . 'TIMESTAMPDIFF(SECOND, v.envoye_le, NOW()) AS secondes_depuis_envoi '
                . 'FROM utilisateur u '
                . 'JOIN adherent a ON a.id_utilisateur = u.id_utilisateur '
                . 'LEFT JOIN code_verification_email v ON v.id_utilisateur = u.id_utilisateur '
                . 'WHERE u.email = :email AND u.email_verifie = 0 LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (!$user) {
                unset($_SESSION['pending_email']);
                setFlash('error', 'Cette adresse ne nécessite plus de vérification.');
                redirectTo('login');
            }

            if (
                $user['secondes_depuis_envoi'] !== null
                && (int) $user['secondes_depuis_envoi'] < 60
            ) {
                setFlash('error', 'Patientez une minute avant de demander un nouveau code.');
                redirectTo('verify');
            }

            $code = newVerificationCode();
            storeVerificationCode($database, (int) $user['id_utilisateur'], $code);
            try {
                sendVerificationCode($email, $user['prenom'], $code);
                setFlash('success', 'Un nouveau code vient de vous être envoyé.');
            } catch (MailException | RuntimeException $exception) {
                error_log('Échec du renvoi du code de vérification : ' . $exception->getMessage());
                setFlash('error', 'Le nouveau code n’a pas pu être envoyé. Vérifiez la configuration SMTP.');
            }
            redirectTo('verify');
        }

        if ($action === 'forgot') {
            $email = trim((string) ($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                setFlash('error', 'Saisissez une adresse e-mail valide.');
                redirectTo('login');
            }

            $statement = $database->prepare(
                'SELECT u.id_utilisateur, a.prenom FROM utilisateur u '
                . 'JOIN adherent a ON a.id_utilisateur = u.id_utilisateur '
                . 'WHERE u.email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (!$user) {
                setFlash('success', 'Si cette adresse existe, un code de réinitialisation a été envoyé.');
                redirectTo('login');
            }

            $code = newVerificationCode();
            storeVerificationCode($database, (int) $user['id_utilisateur'], $code);

            try {
                sendPasswordResetCode($email, $user['prenom'], $code);
                $_SESSION['pending_email'] = $email;
                setFlash('success', 'Si cette adresse existe, un code de réinitialisation a été envoyé.');
            } catch (MailException | RuntimeException $exception) {
                error_log('Échec de l’envoi du code de réinitialisation : ' . $exception->getMessage());
                setFlash('error', 'Le code n’a pas pu être envoyé. Vérifiez la configuration SMTP.');
            }
            redirectTo('login');
        }

        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            setFlash('success', 'Vous êtes déconnecté(e).');
            redirectTo('login');
        }

        setFlash('error', 'Cette action n’est pas reconnue.');
        redirectTo('login');
    } catch (PDOException $exception) {
        if (isset($database) && $database->inTransaction()) {
            $database->rollBack();
        }
        error_log('Erreur de base de données : ' . $exception->getMessage());

        if ($exception->getCode() === '23000') {
            $_SESSION['form_values'] = [
                'prenom' => trim((string) ($_POST['prenom'] ?? '')),
                'nom' => trim((string) ($_POST['nom'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'telephone' => trim((string) ($_POST['telephone'] ?? '')),
            ];
            setFlash('error', 'Cet identifiant ou cette adresse e-mail est déjà utilisé(e).');
            redirectTo('register');
        }

        setFlash('error', 'Le service est momentanément indisponible. Réessayez plus tard.');
        redirectTo('login');
    } catch (RuntimeException $exception) {
        error_log('Configuration de l’application incomplète : ' . $exception->getMessage());
        setFlash('error', 'La configuration de l’application est incomplète. Contactez la bibliothèque.');
        redirectTo('login');
    }
}

$view = (string) ($_GET['view'] ?? (isset($_SESSION['user']) ? 'account' : 'login'));
if (!in_array($view, ['login', 'register', 'verify', 'account'], true)) {
    $view = 'login';
}
if ($view === 'account' && !isset($_SESSION['user'])) {
    $view = 'login';
}
if ($view === 'verify' && empty($_SESSION['pending_email'])) {
    $view = 'register';
}

$flash = $_SESSION['flash'] ?? null;
$formValues = $_SESSION['form_values'] ?? [];
unset($_SESSION['flash'], $_SESSION['form_values']);
$user = $_SESSION['user'] ?? null;
$pageTitles = [
    'login' => 'Connexion',
    'register' => 'Créer un compte',
    'verify' => 'Vérification de l’adresse e-mail',
    'account' => 'Mon espace',
];
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#24483f">
  <title><?= escape($pageTitles[$view]) ?> — La bibliothèque</title>
  <link rel="stylesheet" href="auth.css">
  <style>
    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 50; }
    .modal[aria-hidden="false"] { display: flex; }
    .modal__panel { background: #fff; padding: 1.5rem; border-radius: 8px; width: min(90vw, 420px); }
    .text-button { background: transparent; border: 0; padding: 0; color: #24483f; text-decoration: underline; cursor: pointer; }
  </style>
</head>
<body>
  <main class="auth-layout">
    <section class="auth-aside" aria-label="La bibliothèque">
      <a class="auth-brand" href="index.html">
        <span class="auth-brand__mark" aria-hidden="true">L</span>
        <span>La bibliothèque</span>
      </a>
      <div class="auth-aside__copy">
        <p class="auth-eyebrow">Le plaisir de lire, tout simplement</p>
        <h1>Une histoire<br>vous attend.</h1>
        <p>Connectez-vous pour retrouver votre espace et découvrir les livres de la bibliothèque.</p>
      </div>
      <a class="auth-aside__home" href="index.html">Découvrir la bibliothèque <span aria-hidden="true">↗</span></a>
    </section>

    <section class="auth-main">
      <div class="auth-card">
        <a class="auth-mobile-brand" href="index.html">La bibliothèque</a>

        <?php if ($flash): ?>
          <p class="notice notice--<?= escape($flash['type']) ?>" role="status"><?= escape($flash['message']) ?></p>
        <?php endif; ?>

        <?php if ($view === 'login'): ?>
          <p class="auth-eyebrow auth-eyebrow--dark">Votre espace bibliothèque</p>
          <h2>Heureux de vous revoir.</h2>
          <p class="auth-intro">Connectez-vous avec votre adresse e-mail et votre mot de passe.</p>
          <form class="auth-form" method="post" action="index.php?view=login">
            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="login">
            <label for="login-email">Adresse e-mail</label>
            <input id="login-email" name="email" type="email" autocomplete="email" maxlength="100" required value="<?= escape((string) ($formValues['email'] ?? '')) ?>">
            <label for="login-password">Mot de passe</label>
            <input id="login-password" name="password" type="password" autocomplete="current-password" required>
            <button class="auth-button" type="submit">Se connecter <span aria-hidden="true">→</span></button>
          </form>
          <p class="auth-switch">Vous n’avez pas encore de compte ? <a href="index.php?view=register">Créer un compte</a></p>
          <p class="auth-switch"><a href="#" id="forgot-link">Mot de passe oublié ?</a></p>

        <?php elseif ($view === 'register'): ?>
          <p class="auth-eyebrow auth-eyebrow--dark">Rejoignez-nous</p>
          <h2>Créer votre compte.</h2>
          <p class="auth-intro">Quelques informations pour préparer votre espace lecteur.</p>
          <form class="auth-form auth-form--register" method="post" action="index.php?view=register">
            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="register">
            <div class="auth-form__row">
              <div>
                <label for="register-first-name">Prénom</label>
                <input id="register-first-name" name="prenom" autocomplete="given-name" maxlength="50" required value="<?= escape((string) ($formValues['prenom'] ?? '')) ?>">
              </div>
              <div>
                <label for="register-last-name">Nom</label>
                <input id="register-last-name" name="nom" autocomplete="family-name" maxlength="50" required value="<?= escape((string) ($formValues['nom'] ?? '')) ?>">
              </div>
            </div>
            <label for="register-email">Adresse e-mail</label>
            <input id="register-email" name="email" type="email" autocomplete="email" maxlength="100" required value="<?= escape((string) ($formValues['email'] ?? '')) ?>">
            <label for="register-phone">Téléphone <span class="optional">(facultatif)</span></label>
            <input id="register-phone" name="telephone" type="tel" autocomplete="tel" maxlength="20" value="<?= escape((string) ($formValues['telephone'] ?? '')) ?>">
            <label for="register-password">Mot de passe <span class="optional">(10 caractères minimum)</span></label>
            <input id="register-password" name="password" type="password" autocomplete="new-password" minlength="10" required>
            <label for="register-password-confirm">Confirmer le mot de passe</label>
            <input id="register-password-confirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required>
            <button class="auth-button" type="submit">Créer mon compte <span aria-hidden="true">→</span></button>
          </form>
          <p class="auth-switch">Vous avez déjà un compte ? <a href="index.php?view=login">Se connecter</a></p>

        <?php elseif ($view === 'verify'): ?>
          <p class="auth-eyebrow auth-eyebrow--dark">Encore une étape</p>
          <h2>Vérifiez votre e-mail.</h2>
          <p class="auth-intro">Saisissez le code à 8 chiffres envoyé à <strong><?= escape((string) $_SESSION['pending_email']) ?></strong>. Il est valable 10 minutes.</p>
          <form class="auth-form" method="post" action="index.php?view=verify">
            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="verify">
            <label for="verification-code">Code de vérification</label>
            <input id="verification-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{8}" minlength="8" maxlength="8" required>
            <button class="auth-button" type="submit">Confirmer mon adresse <span aria-hidden="true">→</span></button>
          </form>
          <form class="resend-form" method="post" action="index.php?view=verify">
            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="resend">
            <button class="text-button" type="submit">Renvoyer un code</button>
          </form>
          <p class="auth-switch"><a href="index.php?view=login">Retour à la connexion</a></p>

        <?php else: ?>
          <p class="auth-eyebrow auth-eyebrow--dark">Votre espace bibliothèque</p>
          <h2>Bonjour, <?= escape($user['login']) ?>.</h2>
          <p class="auth-intro">Vous êtes connecté(e). Votre espace lecteur est prêt à accueillir vos prochaines découvertes.</p>
          <form method="post" action="index.php">
            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="logout">
            <button class="auth-button" type="submit">Se déconnecter</button>
          </form>
        <?php endif; ?>
      </div>
      <p class="auth-footer">© 2026 La bibliothèque · <a href="index.html">Retour au site</a></p>
    </section>
  </main>

  <div class="modal" id="forgot-modal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal__panel" role="document">
      <h3>Réinitialiser le mot de passe</h3>
      <p>Entrez votre adresse e-mail pour recevoir un code de réinitialisation.</p>
      <form method="post" action="index.php">
        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="forgot">
        <label for="forgot-email">Adresse e-mail</label>
        <input id="forgot-email" name="email" type="email" autocomplete="email" required>
        <div style="display:flex;gap:8px;margin-top:1rem;align-items:center;">
          <button class="auth-button" type="submit">Envoyer le code</button>
          <button type="button" class="text-button" id="forgot-cancel">Annuler</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function () {
      const forgotLink = document.getElementById('forgot-link');
      const modal = document.getElementById('forgot-modal');
      const cancel = document.getElementById('forgot-cancel');
      const emailField = document.getElementById('forgot-email');

      if (forgotLink && modal) {
        forgotLink.addEventListener('click', function (event) {
          event.preventDefault();
          modal.setAttribute('aria-hidden', 'false');
          if (emailField) emailField.focus();
        });
      }

      if (cancel && modal) {
        cancel.addEventListener('click', function () {
          modal.setAttribute('aria-hidden', 'true');
        });
      }

      if (modal) {
        modal.addEventListener('click', function (event) {
          if (event.target === modal) {
            modal.setAttribute('aria-hidden', 'true');
          }
        });
      }
    }());
  </script>
</body>
</html>
