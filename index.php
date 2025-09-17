<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once "config/db.php";

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM taskora_projects WHERE user_id = ?");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
// --- minimal task counts (safe insert) ---
$counts = ['ready'=>0, 'progress'=>0, 'review'=>0, 'done'=>0];
foreach($tasks as $t){
  if(isset($counts[$t['status']])) $counts[$t['status']]++;
}
?>


<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <title>Taskora</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="website icon" type="png" src="assets/img/taskora_logo.png" >

  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Frijole&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">


  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</head>
<body>
<header class="header">
  <div class="logo">
    <img src="assets/img/taskora_logo.png" alt="Taskora Logo" height="44">
    <h1>Taskora</h1>
  </div>
  <div class="header-actions">
    <button id="toggleFormBtn" class="button-add add-task">+ Dodaj</button>




<div class="dropdown">
  <a type="button" class="btn  dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
    <img src="./assets/img/default.png" style="width: 44px; border-radius: 50%;" alt="">
</a>
  <form class="dropdown-menu p-4">
    <div class="mb-3">
      <a href="" class="profnav-link">Profil</a>
    </div>
    <div class="mb-3">
      <a href="" class="profnav-link">Ustawienia</a>
    </div>
    <div class="mb-3">
      <a href="logout.php" class="logout-link">Wyloguj</a>
    </div>
  </form>
</div>



  </div>
</header>

<!-- Ukryty formularz -->
<div id="addTaskForm" class="add-task hidden">
  <input type="text" id="taskTitle" placeholder="Tytuł zadania" required>
  <textarea id="taskDesc" placeholder="Opis zadania"></textarea>
  <button id="addTaskBtn" class="btn-primary text-dark">Dodaj</button>
</div>

<div class="tastat stats">

  <div class="task-stats box-statss row p-2" aria-hidden="false" style=" text-align: center;">
    <div class="stat box-statss col">To do: <strong><?= $counts['ready'] ?></strong></div>
    <div class="stat box-statss col">Progress: <strong><?= $counts['progress'] ?></strong></div>
    <div class="stat box-statss col">Review: <strong><?= $counts['review'] ?></strong></div>
    <div class="stat box-statss col">Done: <strong><?= $counts['done'] ?></strong></div>
  </div>
  
</div>


<main class="board row g-2 p-3 py-3">
  
<?php
$columns = [
  "ready" => "📝Task Ready",
  "progress" => "🔄 In Progress",
  "review" => "👁‍🗨 Needs Review",
  "done" => "✅ Done"
];
foreach($columns as $key=>$title): ?> 
  <div class="column">
    <h2 class="text-center"><?= $title ?></h2>
    <div id="<?= $key ?>" class="task-list">
      <?php foreach($tasks as $task): if($task['status'] == $key): ?>
        <div class="task" data-id="<?= $task['id'] ?>">
          <h3><?= htmlspecialchars($task['title']) ?></h3>
          <p><?= htmlspecialchars($task['description']) ?></p>
          <div class="task-actions">
            <button class="edit-task">✏️</button>
            <button class="delete-task">🗑️</button>
          </div>
        </div>
      <?php endif; endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
</main>

<script src="assets/js/app.js"></script>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

</body>
</html>





