<?php
session_start();
require_once "../config/db.php";

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Brak dostępu"], JSON_UNESCAPED_UNICODE);
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_SESSION['user_id'];
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("DELETE FROM taskora_tasks WHERE id = ? AND user_id = ?");
    $ok = $stmt->execute([$id, $user_id]);

    echo json_encode(["success" => (bool)$ok], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(["success" => false], JSON_UNESCAPED_UNICODE);
