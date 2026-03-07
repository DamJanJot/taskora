<?php
session_start();
require_once "../config/db.php";

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Brak dostępu"], JSON_UNESCAPED_UNICODE);
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Metoda niedozwolona"], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

if ($project_id <= 0) {
    echo json_encode(["success" => false, "error" => "Brak ID projektu"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // verify ownership
    $stmt = $pdo->prepare("SELECT id FROM taskora_projects WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$project_id, $user_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Nie znaleziono projektu"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->beginTransaction();

    // delete tasks in project
    $stmtT = $pdo->prepare("DELETE FROM taskora_tasks WHERE project_id = ? AND user_id = ?");
    $stmtT->execute([$project_id, $user_id]);

    // delete project
    $stmtP = $pdo->prepare("DELETE FROM taskora_projects WHERE id = ? AND user_id = ?");
    $stmtP->execute([$project_id, $user_id]);

    $pdo->commit();

    echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Błąd bazy danych"], JSON_UNESCAPED_UNICODE);
}
