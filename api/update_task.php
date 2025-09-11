<?php
session_start();
require_once "../config/db.php";
if(!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false]);
    exit;
}
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $id = $_POST['id'];
    $title = $_POST['title'] ?? null;
    $description = $_POST['description'] ?? null;
    $status = $_POST['status'] ?? null;

    if($title !== null && $description !== null) {
        $stmt = $pdo->prepare("UPDATE taskora_projects SET title=?, description=? WHERE id=? AND user_id=?");
        $stmt->execute([$title, $description, $id, $user_id]);
    } elseif($status !== null) {
        $stmt = $pdo->prepare("UPDATE taskora_projects SET status=? WHERE id=? AND user_id=?");
        $stmt->execute([$status, $id, $user_id]);
    }
    echo json_encode(["success" => true]);
    exit;
}
echo json_encode(["success" => false]);
