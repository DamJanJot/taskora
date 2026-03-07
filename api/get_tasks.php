<?php
require_once "../config/db.php";
require_once "../includes/helpers.php";
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Brak dostępu'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

$params = [$user_id];
$where = "WHERE t.user_id = ?";
if ($project_id > 0) {
    $where .= " AND t.project_id = ?";
    $params[] = $project_id;
}


$orderSql = "ORDER BY 
        CASE
            WHEN t.status IN ('ready','todo','to_do') THEN 1
            WHEN t.status IN ('progress','in_progress') THEN 2
            WHEN t.status = 'review' THEN 3
            WHEN t.status = 'done' THEN 4
            ELSE 99
        END,
        COALESCE(t.sort_order, 2147483647),
        t.created_at DESC,
        t.id DESC";


$sql = "SELECT t.*, u.imie AS creator_name, a.imie AS assigned_name
        FROM taskora_tasks t
        JOIN uzytkownicy u ON u.id = t.user_id
        LEFT JOIN uzytkownicy a ON a.id = t.assigned_to
        $where
        $orderSql";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} catch (PDOException $e) {
    // Older DB without project_id/sort_order: try to add the column(s) and retry once.
    if (stripos($e->getMessage(), 'project_id') !== false || stripos($e->getMessage(), 'sort_order') !== false) {
        try {
            $pdo->exec("ALTER TABLE `taskora_tasks` ADD COLUMN IF NOT EXISTS `project_id` INT NULL AFTER `user_id`");
            try {
                $pdo->exec("CREATE INDEX `idx_taskora_tasks_project_id` ON `taskora_tasks`(`project_id`)");
            } catch (Throwable $ignoredIndexError) {
                // ignore duplicate / unsupported
            }

            try {
                $pdo->exec("ALTER TABLE `taskora_tasks` ADD COLUMN IF NOT EXISTS `sort_order` INT NULL DEFAULT NULL AFTER `status`");
            } catch (Throwable $ignore) {
                // ignore
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable $e2) {
            http_response_code(500);
            echo json_encode(['error' => 'Brak kolumny project_id/sort_order. Odpal migrację taskora_v3.sql.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Błąd bazy przy pobieraniu tasków.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize status + render description
foreach ($tasks as &$t) {
    $t['status'] = taskora_normalize_status($t['status'] ?? 'ready');
    $t['description'] = $t['description'] ?? '';
    $t['description_html'] = taskora_render_description($t['description']);
}
unset($t);

echo json_encode($tasks, JSON_UNESCAPED_UNICODE);
