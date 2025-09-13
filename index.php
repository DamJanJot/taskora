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
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <title>Taskora</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="website icon" type="png" src="assets/img/taskora_logo.png" >
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</head>
<body>
<header class="header">
  <div class="logo">
    <img src="assets/img/taskora_logo.png" alt="Taskora Logo" height="40">
    <h1>Taskora</h1>
  </div>
  <div class="header-actions">
    <button id="toggleFormBtn" class="btn-primary">+ Dodaj Task</button>
    <a href="logout.php" class="logout-link">Wyloguj</a>
  </div>
</header>

<!-- Ukryty formularz -->
<div id="addTaskForm" class="add-task hidden">
  <input type="text" id="taskTitle" placeholder="Tytuł zadania" required>
  <textarea id="taskDesc" placeholder="Opis zadania"></textarea>
  <button id="addTaskBtn" class="btn-primary">Dodaj</button>
</div>

<main class="board">
<?php
$columns = [
  "ready" => "Task Ready",
  "progress" => "In Progress",
  "review" => "Needs Review",
  "done" => "Done"
];
foreach($columns as $key=>$title): ?>
  <div class="column">
    <h2><?= $title ?></h2>
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
</body>
</html>
