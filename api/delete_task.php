<?php
session_start();
require_once "../config/db.php";
if(!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false]);
    exit;
}
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $id = intval($_POST['id']);
    $stmt = $pdo->prepare("DELETE FROM taskora_projects WHERE id = ? AND user_id = ?");
    $ok = $stmt->execute([$id, $user_id]);
    echo json_encode(["success" => $ok]);
    exit;
}
echo json_encode(["success" => false]);
