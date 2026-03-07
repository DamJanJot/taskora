<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];

try {
    // Use utf8mb4 to correctly store/display Polish characters and emojis.
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Force connection charset/collation (helps when server defaults are different).
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_polish_ci");

    // ---- Lightweight auto-migration (keeps older DBs working) ----
    // Some earlier Taskora DBs don't have `project_id` in `taskora_tasks`.
    // Without it, tasks can't be assigned to projects and the board will appear empty.
    // This block adds the missing column/index if possible.
    try {
        $dbNameSafe = str_replace('`', '``', $dbname);
        $colCheck = $pdo->prepare(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'taskora_tasks' AND COLUMN_NAME = 'project_id'"
        );
        $colCheck->execute([$dbname]);
        $hasProjectId = (int)$colCheck->fetchColumn() > 0;

        if (!$hasProjectId) {
            // Add column + index (safe to run once).
            $pdo->exec("ALTER TABLE `taskora_tasks` ADD COLUMN `project_id` INT NULL AFTER `user_id`");
            $pdo->exec("CREATE INDEX `idx_taskora_tasks_project_id` ON `taskora_tasks`(`project_id`)");
        }
    } catch (Throwable $e) {
        // If DB user has no ALTER privileges, we just skip.
        // The app will still work once the migration SQL is applied manually.
    }
} catch (PDOException $e) {
    die("Błąd połączenia: " . $e->getMessage());
}
