<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html; charset=utf-8');

$userId = (int)$_SESSION['user_id'];

// Ensure uploads directory exists.
$uploadsDir = __DIR__ . '/uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}

// --- handle update ---
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nick = trim((string)($_POST['nick'] ?? ''));
    $opis = trim((string)($_POST['opis'] ?? ''));

    // Optional avatar upload
    $newAvatarPath = null;
    if (!empty($_FILES['zdjecie_profilowe']) && ($_FILES['zdjecie_profilowe']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmp = $_FILES['zdjecie_profilowe']['tmp_name'];
        $original = (string)($_FILES['zdjecie_profilowe']['name'] ?? '');

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            $flash = ['type' => 'danger', 'msg' => 'Dozwolone formaty avataru: JPG, PNG, WEBP.'];
        } else {
            $fileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $targetAbs = $uploadsDir . '/' . $fileName;
            if (@move_uploaded_file($tmp, $targetAbs)) {
                $newAvatarPath = 'uploads/' . $fileName; // stored as relative path
            } else {
                $flash = ['type' => 'danger', 'msg' => 'Nie udało się zapisać pliku. Spróbuj ponownie.'];
            }
        }
    }

    if ($flash === null) {
        if ($newAvatarPath !== null) {
            $stmt = $pdo->prepare("UPDATE uzytkownicy SET nick = :nick, opis = :opis, zdjecie_profilowe = :av WHERE id = :id");
            $stmt->execute(['nick' => $nick, 'opis' => $opis, 'av' => $newAvatarPath, 'id' => $userId]);
            $_SESSION['user_avatar'] = $newAvatarPath;
        } else {
            $stmt = $pdo->prepare("UPDATE uzytkownicy SET nick = :nick, opis = :opis WHERE id = :id");
            $stmt->execute(['nick' => $nick, 'opis' => $opis, 'id' => $userId]);
        }

        $flash = ['type' => 'success', 'msg' => 'Profil zaktualizowany.'];
    }
}

// --- fetch user ---
$stmt = $pdo->prepare("SELECT imie, nazwisko, email, zdjecie_profilowe, nick, opis FROM uzytkownicy WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    // If the auth table differs, fail gracefully.
    http_response_code(500);
    echo 'Nie znaleziono użytkownika w bazie (tabela uzytkownicy).';
    exit;
}

$defaultAvatar = 'assets/img/default.png';
$avatarPath = $defaultAvatar;
if (!empty($user['zdjecie_profilowe'])) {
    $candidate = (string)$user['zdjecie_profilowe'];
    if (preg_match('~^https?://~i', $candidate)) {
        $avatarPath = $candidate;
    } else {
        $candidate = ltrim(str_replace('\\', '/', $candidate), '/');
        if (file_exists(__DIR__ . '/' . $candidate)) {
            $avatarPath = $candidate;
        }
    }
}
?>

<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profil - Taskora</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>

<header class="header">
  <div class="logo">
    <img src="assets/img/taskora_logo.png" alt="Taskora Logo" height="44">
    <h1>Taskora</h1>
  </div>
  <div class="header-actions">
    <a href="index.php" class="button-add" style="text-decoration:none; display:inline-block;">← Tablica</a>

    <div class="dropdown">
      <a type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
        <img src="<?= htmlspecialchars($avatarPath) ?>" style="width: 44px; height:44px; object-fit:cover; border-radius: 50%;" alt="Avatar">
      </a>
      <form class="dropdown-menu p-4">
        <div class="mb-3">
          <a href="profil.php" class="profnav-link">Profil</a>
        </div>
        <div class="mb-3">
          <a href="index.php" class="profnav-link">Tablica</a>
        </div>
        <div class="mb-3">
          <a href="logout.php" class="logout-link">Wyloguj</a>
        </div>
      </form>
    </div>
  </div>
</header>

<main class="container" style="max-width: 820px;">
  <div class="row justify-content-center mt-4">
    <div class="col-12 col-lg-10">

      <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" role="alert">
          <?= htmlspecialchars($flash['msg']) ?>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Zdjęcie profilowe" style="width: 86px; height: 86px; object-fit: cover; border-radius: 50%; border: 2px solid rgba(0,0,0,0.08);">
            <div>
              <h2 class="h4 mb-1"><?= htmlspecialchars(trim(($user['imie'] ?? '') . ' ' . ($user['nazwisko'] ?? ''))) ?></h2>
              <div class="text-muted"><?= htmlspecialchars($user['email'] ?? '') ?></div>
              <?php if (!empty($user['nick'])): ?>
                <div class="mt-1"><span class="badge text-bg-secondary">@<?= htmlspecialchars($user['nick']) ?></span></div>
              <?php endif; ?>
            </div>
          </div>

          <hr>

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <div class="fw-semibold">Opis</div>
              <div class="text-muted" style="white-space: pre-wrap;">
                <?= htmlspecialchars($user['opis'] ?? '') ?>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <div class="fw-semibold">Szybkie akcje</div>
              <div class="d-flex gap-2 flex-wrap mt-2">
                <a class="btn btn-outline-primary" href="index.php">Przejdź do tablicy</a>
                <a class="btn btn-outline-danger" href="logout.php">Wyloguj</a>
              </div>
            </div>
          </div>

          <hr>

          <h3 class="h5 mb-3">Edytuj profil</h3>
          <form method="post" enctype="multipart/form-data" class="row g-3">
            <div class="col-12">
              <label class="form-label" for="zdjecie_profilowe">Avatar</label>
              <input id="zdjecie_profilowe" type="file" name="zdjecie_profilowe" class="form-control" accept="image/png,image/jpeg,image/webp">
              <div class="form-text">Zalecane: kwadratowe zdjęcie, max ~2MB.</div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="nick">Nick</label>
              <input id="nick" type="text" name="nick" class="form-control" value="<?= htmlspecialchars($user['nick'] ?? '') ?>" maxlength="64">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="opis">Opis</label>
              <textarea id="opis" name="opis" class="form-control" rows="3" maxlength="500"><?= htmlspecialchars($user['opis'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
              <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
