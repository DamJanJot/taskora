<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once "config/db.php";
require_once "includes/helpers.php";

header('Content-Type: text/html; charset=utf-8');

$user_id = (int)$_SESSION['user_id'];
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

// --- current user (for avatar / header) ---
$currentUser = null;
try {
    $stmtU = $pdo->prepare("SELECT id, imie, nazwisko, nick, zdjecie_profilowe FROM uzytkownicy WHERE id = ? LIMIT 1");
    $stmtU->execute([$user_id]);
    $currentUser = $stmtU->fetch();
} catch (PDOException $e) {
    $currentUser = null;
}
$defaultAvatar = 'assets/img/default.png';
$avatarPath = $defaultAvatar;
if ($currentUser && !empty($currentUser['zdjecie_profilowe'])) {
    $candidate = (string)$currentUser['zdjecie_profilowe'];
    if (preg_match('~^https?://~i', $candidate)) {
        $avatarPath = $candidate;
    } else {
        $candidate = ltrim(str_replace('\\', '/', $candidate), '/');
        if (file_exists(__DIR__ . '/' . $candidate)) {
            $avatarPath = $candidate;
        }
    }
}

// Fetch projects
$stmtP = $pdo->prepare("SELECT * FROM taskora_projects WHERE user_id = ? ORDER BY updated_at DESC, id DESC");
$stmtP->execute([$user_id]);
$projects = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// Validate selected project
$activeProject = null;
if ($project_id > 0) {
    foreach ($projects as $p) {
        if ((int)$p['id'] === $project_id) { $activeProject = $p; break; }
    }
    if (!$activeProject) {
        $project_id = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <title>Taskora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Frijole&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="manifest" href="/site.webmanifest">
      <link rel="website icon" type="png" href="./img/logo.png">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</head>
<body>
<header class="header">
  <div class="logo">
    <img src="assets/img/taskora_logo.png" alt="Taskora Logo" height="44">
    <h1>Taskora</h1>
  </div>

  <div class="header-actions">
    <?php if ($project_id > 0): ?>
      <a class="button-add add-task" href="index.php">← Projekty</a>
      <button id="toggleFormBtn" class="button-add add-task">+ Dodaj task</button>
    <?php else: ?>
      <button id="openCreateProject" class="button-add add-task">+ Dodaj projekt</button>
    <?php endif; ?>

    <div class="dropdown">
      <a type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
        <img src="<?= taskora_escape($avatarPath) ?>" style="width: 44px; height:44px; object-fit:cover; border-radius: 50%;" alt="Avatar">
      </a>
      <form class="dropdown-menu p-4">
        <div class="mb-3"><a href="profil.php" class="profnav-link">Profil</a></div>
        <div class="mb-3"><a href="index.php" class="profnav-link">Tablica</a></div>
        <div class="mb-3"><a href="" class="profnav-link">Ustawienia</a></div>
        <div class="mb-3"><a href="logout.php" class="logout-link">Wyloguj</a></div>
      </form>
    </div>
  </div>
</header>

<?php if ($project_id === 0): ?>
  <!-- PROJECTS LIST -->
  <main class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="m-0">Twoje projekty</h2>
      <small class="text-muted">Kliknij projekt, aby wejść do tasków</small>
    </div>

    <div id="projectsGrid" class="row g-3">
      <?php foreach ($projects as $p): ?>
        <?php
          $pid = (int)($p['id'] ?? 0);
          $updatedLabel = '';
          if (!empty($p['updated_at'])) {
            $ts = strtotime((string)$p['updated_at']);
            if ($ts) { $updatedLabel = date('Y-m-d H:i', $ts); }
          }
        ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="project-card project-card--wrap" data-project-id="<?= $pid ?>">
            <div class="project-card__top">
              <a class="project-card__link" href="index.php?project_id=<?= $pid ?>" aria-label="Otwórz projekt">
                <div class="project-card__title"><?= taskora_escape($p['title'] ?? '') ?></div>
              </a>

              <div class="project-card__actions">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary project-edit-btn"
                        data-project-id="<?= $pid ?>"
                        data-project-title="<?= taskora_escape($p['title'] ?? '') ?>"
                        data-project-description="<?= taskora_escape($p['description'] ?? '') ?>"
                        title="Edytuj projekt">
                  Edytuj
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-danger project-delete-btn"
                        data-project-id="<?= $pid ?>"
                        data-project-title="<?= taskora_escape($p['title'] ?? '') ?>"
                        title="Usuń projekt">
                  Usuń
                </button>
              </div>
            </div>

            <a class="project-card__link project-card__body" href="index.php?project_id=<?= $pid ?>">
              <div class="project-card__desc"><?= taskora_render_description($p['description'] ?? '') ?></div>

              <div class="project-card__progress">
                <div class="progress" role="progressbar" aria-label="Postęp">
                  <div class="progress-bar" style="width: 0%"></div>
                </div>
                <div class="project-card__meta">
                  <span class="pct">0%</span>
                  <span class="counts">0/0</span>
                  <?php if ($updatedLabel !== ''): ?>
                    <span class="updated">Aktualizacja: <?= taskora_escape($updatedLabel) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (count($projects) === 0): ?>
        <div class="col-12">
          <div class="alert alert-info">Nie masz jeszcze projektów. Kliknij <b>+ Dodaj projekt</b> w prawym górnym rogu.</div>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Create Project Modal -->
  <div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nowy projekt</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Tytuł</label>
            <input id="projectTitle" class="form-control" placeholder="np. Kartoteka sprzętu" />
          </div>
          <div class="mb-2">
            <label class="form-label">Opis</label>
            <textarea id="projectDesc" class="form-control" rows="5" placeholder="Możesz użyć: **pogrubienie**, listy - punkt 1"></textarea>
          </div>
          <div class="text-muted small">Obsługiwane: Enter = nowa linia, **pogrubienie**, listy (- пункт).</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button id="createProjectBtn" class="btn btn-primary">Utwórz</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Project Modal -->
  <div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edytuj projekt</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="editProjectId" />
          <div class="mb-2">
            <label class="form-label">Tytuł</label>
            <input id="editProjectTitle" class="form-control" placeholder="Tytuł projektu" />
          </div>
          <div class="mb-2">
            <label class="form-label">Opis</label>
            <textarea id="editProjectDesc" class="form-control" rows="5" placeholder="Opis projektu"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button id="saveProjectBtn" class="btn btn-primary">Zapisz</button>
        </div>
      </div>
    </div>
  </div>

  
  <!-- Delete Project Modal -->
  <div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Usuń projekt</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">Na pewno chcesz usunąć projekt:</p>
          <div class="alert alert-warning mb-0">
            <b id="deleteProjectName"></b><br>
            <small>To usunie również wszystkie taski w tym projekcie.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button id="confirmDeleteProjectBtn" class="btn btn-danger">Usuń</button>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- TASK BOARD -->
  <div class="container py-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h2 class="m-0"><?= taskora_escape($activeProject['title'] ?? '') ?></h2>
        <div class="text-muted small"><?= taskora_render_description($activeProject['description'] ?? '') ?></div>
      </div>

      <div class="task-stats box-statss row p-2 m-0" aria-hidden="false" style=" text-align: center; min-width: 320px;">
        <div class="stat box-statss col">To do: <strong id="cnt-ready">0</strong></div>
        <div class="stat box-statss col">Progress: <strong id="cnt-progress">0</strong></div>
        <div class="stat box-statss col">Review: <strong id="cnt-review">0</strong></div>
        <div class="stat box-statss col">Done: <strong id="cnt-done">0</strong></div>
      </div>
    </div>
  </div>

  <!-- Add Task Form -->
  <div id="addTaskForm" class="add-task hidden">
    <input type="text" id="taskTitle" placeholder="Tytuł zadania" required>
      <div class="row g-2 mt-2">
        <div class="col-12 col-sm-6">
          <label class="form-label mb-1" for="taskStatus">Status</label>
          <select id="taskStatus" class="form-select">
            <option value="ready" selected>To do</option>
            <option value="progress">In Progress</option>
            <option value="review">Needs Review</option>
            <option value="done">Done</option>
          </select>
        </div>
      </div>
    <div class="mini-toolbar">
      <button type="button" class="mini-btn" data-md="bold" title="Pogrubienie"><b>B</b></button>
      <button type="button" class="mini-btn" data-md="ul" title="Lista punktowana">• Lista</button>
      <button type="button" class="mini-btn" data-md="ol" title="Lista numerowana">1. Lista</button>
    </div>
    <textarea id="taskDesc" placeholder="Opis zadania (Enter = nowa linia, **pogrubienie**, listy: - punkt)" rows="6"></textarea>
    <button id="addTaskBtn" class="btn btn-primary">Dodaj</button>
  </div>

  <main class="board row g-2 p-3 py-3">
    <?php
      $columns = [
        "ready" => "📝 To do",
        "progress" => "🔄 In Progress",
        "review" => "👁‍🗨 Needs Review",
        "done" => "✅ Done"
      ];
      foreach($columns as $key=>$title):
    ?>
      <div class="column">
        <h2 class="text-center"><?= $title ?></h2>
        <div id="<?= $key ?>" class="task-list"></div>
      </div>
    <?php endforeach; ?>
  </main>

  <!-- Edit Task Modal (dblclick) -->
  <div class="modal fade" id="editTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edytuj task</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="editTaskId" />
          <div class="mb-2">
            <label class="form-label">Tytuł</label>
            <input id="editTaskTitle" class="form-control" />
          </div>

          <div class="mb-2">
            <label class="form-label">Opis</label>
            <div class="mini-toolbar">
              <button type="button" class="mini-btn" data-md="bold" data-target="editTaskDesc"><b>B</b></button>
              <button type="button" class="mini-btn" data-md="ul" data-target="editTaskDesc">• Lista</button>
              <button type="button" class="mini-btn" data-md="ol" data-target="editTaskDesc">1. Lista</button>
            </div>
            <textarea id="editTaskDesc" class="form-control" rows="10"></textarea>
            <div class="text-muted small mt-1">Tip: zaznacz tekst i kliknij <b>B</b>, albo dodaj listę.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button id="deleteTaskBtn" class="btn btn-outline-danger me-auto">Usuń</button>
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button id="saveTaskBtn" class="btn btn-primary">Zapisz</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.TASKORA_PROJECT_ID = <?= (int)$project_id ?>;
  </script>

  <script src="assets/js/taskora-board.js"></script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<?php if ($project_id === 0): ?>
<script src="assets/js/taskora-projects.js"></script>
<?php endif; ?>
</body>
</html>
