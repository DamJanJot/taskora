<?php
require_once "../config/db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Brak dostępu']);
    exit;
}

$sql = "SELECT t.*, u.imie AS creator_name, a.imie AS assigned_name
        FROM taskora_tasks t
        JOIN uzytkownicy u ON u.id = t.user_id
        LEFT JOIN uzytkownicy a ON a.id = t.assigned_to
        ORDER BY t.created_at DESC";
$stmt = $pdo->query($sql);

$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($tasks);
