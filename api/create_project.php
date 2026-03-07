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
    $title = trim((string)($_POST['title'] ?? ''));
    $description = (string)($_POST['description'] ?? '');

    if ($title === '') {
        echo json_encode(["success" => false, "error" => "Podaj tytuł projektu"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO taskora_projects (user_id, title, description) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $title, $description]);

    echo json_encode(["success" => true, "id" => $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(["success" => false], JSON_UNESCAPED_UNICODE);
