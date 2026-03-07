<?php
session_start();
require_once "../config/db.php";
require_once "../includes/helpers.php";

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Brak dostępu"], JSON_UNESCAPED_UNICODE);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$sql = "SELECT p.*,
               COALESCE(SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END),0) AS done_count,
               COALESCE(COUNT(t.id),0) AS total_count
        FROM taskora_projects p
        LEFT JOIN taskora_tasks t ON t.project_id = p.id AND t.user_id = p.user_id
        WHERE p.user_id = ?
        GROUP BY p.id
        ORDER BY p.updated_at DESC, p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $total = (int)$r['total_count'];
    $done = (int)$r['done_count'];
    $r['progress_percent'] = $total > 0 ? round(($done / $total) * 100) : 0;
    $r['title'] = $r['title'] ?? '';
    $r['description'] = $r['description'] ?? '';
    $r['description_html'] = taskora_render_description($r['description']);
}
unset($r);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
