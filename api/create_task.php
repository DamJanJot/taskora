<?php
session_start();
require_once "../config/db.php";
if(!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false]);
    exit;
}
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $stmt = $pdo->prepare("INSERT INTO taskora_projects (user_id, title, description, status) VALUES (?, ?, ?, 'ready')");
    $stmt->execute([$user_id, $title, $description]);
    echo json_encode(["success" => true, "id" => $pdo->lastInsertId()]);
    exit;
}
echo json_encode(["success" => false]);
