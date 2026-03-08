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

function taskora_ensure_sort_order_column(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $pdo->exec("ALTER TABLE `taskora_tasks` ADD COLUMN IF NOT EXISTS `sort_order` INT NULL DEFAULT NULL AFTER `status`");
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $pdo->exec("CREATE INDEX `idx_taskora_tasks_sort_order` ON `taskora_tasks`(`sort_order`)");
    } catch (Throwable $e) {
        // ignore duplicate / unsupported
    }
}

function taskora_update_status_row(PDO $pdo, int $id, int $user_id, string $status, ?int $sortOrder = null, ?int $project_id = null): int {
    $params = [];
    $set = "status = ?";
    $params[] = $status;

    if ($sortOrder !== null) {
        $set .= ", sort_order = ?";
        $params[] = $sortOrder;
    }

    $sql = "UPDATE taskora_tasks SET {$set} WHERE id = ? AND user_id = ?";
    $params[] = $id;
    $params[] = $user_id;

    if ($project_id !== null && $project_id > 0) {
        $sql .= " AND project_id = ?";
        $params[] = $project_id;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->rowCount();
    } catch (PDOException $e) {
        // Backward compatibility for ENUM('todo','in_progress','review','done').
        if ($status === 'progress') {
            $params[0] = 'in_progress';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->rowCount();
        }
        throw $e;
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_SESSION['user_id'];

    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'reorder') {
        $status = taskora_normalize_status($_POST['status'] ?? 'ready');
        $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
        $orderedRaw = (string)($_POST['ordered_ids'] ?? '[]');
        $orderedIds = json_decode($orderedRaw, true);

        if (!is_array($orderedIds) || count($orderedIds) === 0) {
            echo json_encode(["success" => false, "error" => "Brak kolejności"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        taskora_ensure_sort_order_column($pdo);

        try {
            $pdo->beginTransaction();
            $position = 1;
            $updatedAny = 0;
            foreach ($orderedIds as $rawId) {
                $id = (int)$rawId;
                if ($id <= 0) continue;

                $affected = taskora_update_status_row($pdo, $id, $user_id, $status, $position, $project_id > 0 ? $project_id : null);

                // Legacy safety: if row did not match with project filter, retry without it.
                if ($affected === 0 && $project_id > 0) {
                    $affected = taskora_update_status_row($pdo, $id, $user_id, $status, $position, null);
                }

                $updatedAny += $affected;
                $position++;
            }

            if ($updatedAny === 0) {
                throw new RuntimeException('Brak zaktualizowanych rekordow');
            }

            $pdo->commit();
            echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Błąd zapisu kolejności"], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(["success" => false, "error" => "Brak ID"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : null;
    $description = isset($_POST['description']) ? (string)$_POST['description'] : null;
    $status = isset($_POST['status']) ? taskora_normalize_status($_POST['status']) : null;

    if ($title !== null || $description !== null) {
        // update title/description
        $sqlParts = [];
        $params = [];
        if ($title !== null) { $sqlParts[] = "title = ?"; $params[] = $title; }
        if ($description !== null) { $sqlParts[] = "description = ?"; $params[] = $description; }
        $params[] = $id;
        $params[] = $user_id;

        $stmt = $pdo->prepare("UPDATE taskora_tasks SET " . implode(", ", $sqlParts) . " WHERE id = ? AND user_id = ?");
        $stmt->execute($params);

        echo json_encode([
            "success" => true,
            "description_html" => taskora_render_description($description ?? '')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($status !== null) {
        taskora_ensure_sort_order_column($pdo);
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : null;
        $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

        try {
            $affected = taskora_update_status_row($pdo, $id, $user_id, $status, $sortOrder, $project_id > 0 ? $project_id : null);
            if ($affected === 0 && $project_id > 0) {
                $affected = taskora_update_status_row($pdo, $id, $user_id, $status, $sortOrder, null);
            }

            if ($affected === 0) {
                throw new RuntimeException('Brak zaktualizowanego rekordu');
            }

            echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Błąd zmiany statusu"], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    echo json_encode(["success" => false, "error" => "Brak danych do aktualizacji"], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(["success" => false], JSON_UNESCAPED_UNICODE);
