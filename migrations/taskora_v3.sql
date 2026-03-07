-- Taskora v3 migration: Projects + utf8mb4 + project_id in tasks + status mapping
-- Make a backup before running.

-- 1) Ensure database uses utf8mb4 (replace DB name)
-- ALTER DATABASE `YOUR_DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;

-- 2) Create projects table if missing
CREATE TABLE IF NOT EXISTS `taskora_projects` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_taskora_projects_user` (`user_id`),
  CONSTRAINT `fk_taskora_projects_user` FOREIGN KEY (`user_id`) REFERENCES `uzytkownicy`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- 3) Add project_id to tasks (if missing)
ALTER TABLE `taskora_tasks`
  ADD COLUMN IF NOT EXISTS `project_id` INT UNSIGNED NULL AFTER `user_id`;

-- 4) Backfill: if you previously stored tasks in taskora_projects (old bug), you can import them into taskora_tasks.
-- (Optional) Uncomment if needed:
-- INSERT INTO taskora_tasks (user_id, project_id, title, description, status, priority, created_at, updated_at)
-- SELECT user_id, NULL, title, description, status, 'medium', created_at, updated_at
-- FROM taskora_projects
-- WHERE title IS NOT NULL;

-- 5) Ensure at least 1 project per user and attach tasks without project_id to the first project
-- Create a default project for each user that doesn't have one
INSERT INTO taskora_projects (user_id, title, description)
SELECT DISTINCT t.user_id, 'Mój projekt', 'Automatycznie utworzony projekt'
FROM taskora_tasks t
LEFT JOIN taskora_projects p ON p.user_id = t.user_id
WHERE p.id IS NULL;

-- Attach tasks without project_id
UPDATE taskora_tasks t
JOIN (
  SELECT user_id, MIN(id) AS pid
  FROM taskora_projects
  GROUP BY user_id
) p ON p.user_id = t.user_id
SET t.project_id = p.pid
WHERE t.project_id IS NULL OR t.project_id = 0;

-- 6) Normalize status values (todo/in_progress -> ready/progress)
UPDATE taskora_tasks SET status='ready' WHERE status IN ('todo','to_do');
UPDATE taskora_tasks SET status='progress' WHERE status IN ('in_progress');

-- 7) (Optional) Change enum to UI statuses (safe if you don't use other values)
-- MariaDB/MySQL 8+: adjust to your server if needed
ALTER TABLE `taskora_tasks`
  MODIFY COLUMN `status` ENUM('ready','progress','review','done') DEFAULT 'ready';

-- 8) Convert tables to utf8mb4 (needed for Polish chars)
ALTER TABLE `taskora_projects` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `taskora_tasks`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
