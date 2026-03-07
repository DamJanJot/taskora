<?php
session_start();
require_once 'config/db.php';

header('Content-Type: text/html; charset=utf-8');

/**
 * Authenticate by login/email/nick and verify password hash.
 */
function taskora_authenticate_user(PDO $pdo, string $login, string $password)
{
    $user = null;
    try {
        // If your table has a "login" column, this query will work.
        $stmt = $pdo->prepare(
            "SELECT id, email, nick, imie, nazwisko, zdjecie_profilowe, haslo
             FROM uzytkownicy
             WHERE email = :l OR nick = :l OR login = :l
             LIMIT 1"
        );
        $stmt->execute(['l' => $login]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        // Fallback when the table does not have a "login" column.
        $stmt = $pdo->prepare(
            "SELECT id, email, nick, imie, nazwisko, zdjecie_profilowe, haslo
             FROM uzytkownicy
             WHERE email = :l OR nick = :l
             LIMIT 1"
        );
        $stmt->execute(['l' => $login]);
        $user = $stmt->fetch();
    }

    if ($user && password_verify($password, (string)$user['haslo'])) {
        return $user;
    }

    return false;
}

/**
 * Save authenticated user into the session.
 */
function taskora_login_user(array $user): void
{
    $_SESSION['user_id'] = (int)$user['id'];
    // Cache a bit of user data for the UI (optional, but speeds up header rendering).
    $_SESSION['user_name'] = trim(($user['imie'] ?? '') . ' ' . ($user['nazwisko'] ?? ''));
    $_SESSION['user_nick'] = $user['nick'] ?? '';
    $_SESSION['user_avatar'] = $user['zdjecie_profilowe'] ?? '';
}

// Optional auto-login for demo environments (Render/env-based).
if (!isset($_SESSION['user_id']) && getenv('DEMO_AUTO_LOGIN') === '1') {
    $demoLogin = trim((string)(getenv('DEMO_LOGIN') ?: ''));
    $demoPassword = (string)(getenv('DEMO_PASSWORD') ?: '');

    if ($demoLogin !== '' && $demoPassword !== '') {
        $demoUser = taskora_authenticate_user($pdo, $demoLogin, $demoPassword);
        if ($demoUser) {
            taskora_login_user($demoUser);
            header('Location: index.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $user = taskora_authenticate_user($pdo, $login, $password);
    if ($user) {
        taskora_login_user($user);
        header("Location: index.php");
        exit;
    } else {
        $error = "Nieprawidłowy login/e-mail lub hasło.";
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Logowanie - Taskora</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <h2>Zaloguj się</h2>
        <?php if(isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="login" placeholder="E-mail lub login" required autocomplete="username">
            <input type="password" name="password" placeholder="Hasło" required>
            <button type="submit">Zaloguj</button>
        </form>
    </div>
</body>
</html>
