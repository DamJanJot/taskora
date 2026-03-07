<?php
session_start();
require_once "../config/db.php";
require_once "../includes/helpers.php";

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Brak dostępu"], JSON_UNESCAPED_UNICODE);
    exit;
}

function taskora_ensure_task_sort_order_column(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $pdo->exec("ALTER TABLE `taskora_tasks` ADD COLUMN IF NOT EXISTS `sort_order` INT NULL DEFAULT NULL AFTER `status`");
    } catch (Throwable $e) {
        // ignore
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_SESSION['user_id'];

    $title = trim((string)($_POST['title'] ?? ''));
    $description = (string)($_POST['description'] ?? '');
    $project_id = (int)($_POST['project_id'] ?? 0);
    $status = taskora_normalize_status($_POST['status'] ?? 'ready');

    if ($title === '') {
        echo json_encode(["success" => false, "error" => "Brak tytułu"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Ensure user owns the project (if project_id given)
    if ($project_id > 0) {
        $chk = $pdo->prepare("SELECT id FROM taskora_projects WHERE id = ? AND user_id = ? LIMIT 1");
        $chk->execute([$project_id, $user_id]);
        if (!$chk->fetchColumn()) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Brak dostępu do projektu"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        // fallback: put into first project if exists
        $pid = $pdo->prepare("SELECT id FROM taskora_projects WHERE user_id = ? ORDER BY id ASC LIMIT 1");
        $pid->execute([$user_id]);
        $project_id = (int)($pid->fetchColumn() ?: 0);
    }

    taskora_ensure_task_sort_order_column($pdo);

    $maxSort = 0;
    try {
        $stmtSort = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM taskora_tasks WHERE user_id = ? AND project_id <=> ? AND status IN (?, ?)");
        $statusAlt = $status === 'progress' ? 'in_progress' : $status;
        $stmtSort->execute([$user_id, $project_id ?: null, $status, $statusAlt]);
        $maxSort = (int)$stmtSort->fetchColumn();
    } catch (Throwable $e) {
        $maxSort = 0;
    }
    $nextSort = $maxSort + 1;

    try {
        $stmt = $pdo->prepare("INSERT INTO taskora_tasks (user_id, project_id, title, description, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $project_id ?: null, $title, $description, $status, $nextSort]);
    } catch (PDOException $e) {
        // Legacy status enum compatibility.
        if ($status === 'progress') {
            try {
                $stmt = $pdo->prepare("INSERT INTO taskora_tasks (user_id, project_id, title, description, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $project_id ?: null, $title, $description, 'in_progress', $nextSort]);
            } catch (Throwable $eLegacy) {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "Błąd bazy przy dodawaniu taska."], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
        // If DB is older and lacks project_id, attempt a one-time ALTER and retry.
        if (stripos($e->getMessage(), 'project_id') !== false || stripos($e->getMessage(), 'sort_order') !== false) {
            try {
                $pdo->exec("ALTER TABLE `taskora_tasks` ADD COLUMN IF NOT EXISTS `project_id` INT NULL AFTER `user_id`");
                try {
                    $pdo->exec("CREATE INDEX `idx_taskora_tasks_project_id` ON `taskora_tasks`(`project_id`)");
                } catch (Throwable $ignoredIndexError) {
                    // ignore duplicate / unsupported
                }
                taskora_ensure_task_sort_order_column($pdo);
                $statusToStore = $status;
                $stmt = $pdo->prepare("INSERT INTO taskora_tasks (user_id, project_id, title, description, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                try {
                    $stmt->execute([$user_id, $project_id ?: null, $title, $description, $statusToStore, $nextSort]);
                } catch (PDOException $eRetry) {
                    if ($status === 'progress') {
                        $stmt->execute([$user_id, $project_id ?: null, $title, $description, 'in_progress', $nextSort]);
                    } else {
                        throw $eRetry;
                    }
                }
            } catch (Throwable $e2) {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "Błąd bazy: brak kolumny project_id. Odpal migrację taskora_v3.sql."], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Błąd bazy przy dodawaniu taska."], JSON_UNESCAPED_UNICODE);
            exit;
        }
        }
    }

    echo json_encode([
        "success" => true,
        "id" => $pdo->lastInsertId(),
        "status" => $status,
        "description_html" => taskora_render_description($description),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(["success" => false], JSON_UNESCAPED_UNICODE);
