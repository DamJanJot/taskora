CREATE TABLE `taskora_tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('todo', 'in_progress', 'review', 'done') DEFAULT 'todo',
  `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `uzytkownicy`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `uzytkownicy`(`id`) ON DELETE SET NULL
);

INSERT INTO `taskora_tasks` (`user_id`, `assigned_to`, `title`, `description`, `status`, `priority`)
VALUES
(1, 2, 'Stworzyć stronę główną', 'Projekt UI i implementacja w React', 'todo', 'high'),
(1, 3, 'Napisać backend API', 'Obsługa logowania i CRUD tasków', 'in_progress', 'medium'),
(2, NULL, 'Sprawdzić SEO', 'Analiza meta tagów i linków', 'review', 'low'),
(3, 1, 'Wdrożyć system powiadomień', 'Powiadomienia dla użytkowników', 'done', 'high');
