<?php
session_start();
require_once 'config/db.php';

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // Allow logging in via e-mail OR login/nick (depending on columns available in your uzytkownicy table).
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

    if ($user && password_verify($password, $user['haslo'])) {
        $_SESSION['user_id'] = (int)$user['id'];
        // Cache a bit of user data for the UI (optional, but speeds up header rendering).
        $_SESSION['user_name'] = trim(($user['imie'] ?? '') . ' ' . ($user['nazwisko'] ?? ''));
        $_SESSION['user_nick'] = $user['nick'] ?? '';
        $_SESSION['user_avatar'] = $user['zdjecie_profilowe'] ?? '';
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
