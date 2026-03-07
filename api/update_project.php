<?php
session_start();
require_once "../config/db.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Brak dostępu"], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Metoda niedozwolona"], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$project_id = (int)($_POST['project_id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$description = (string)($_POST['description'] ?? '');

if ($project_id <= 0) {
    echo json_encode(["success" => false, "error" => "Brak ID projektu"], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($title === '') {
    echo json_encode(["success" => false, "error" => "Podaj tytuł projektu"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE taskora_projects SET title = ?, description = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$title, $description, $project_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        $chk = $pdo->prepare("SELECT id FROM taskora_projects WHERE id = ? AND user_id = ? LIMIT 1");
        $chk->execute([$project_id, $user_id]);
        if (!$chk->fetchColumn()) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Nie znaleziono projektu"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Błąd bazy danych"], JSON_UNESCAPED_UNICODE);
}
