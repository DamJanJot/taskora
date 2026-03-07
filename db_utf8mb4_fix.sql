-- Fix encoding for Polish characters (MySQL/MariaDB)
--
-- IMPORTANT:
-- 1) Make a backup before running.
-- 2) This converts ONLY character set/collation.
--    If text was already saved as "???" it cannot be restored.
--
-- Run inside your database (replace `YOUR_DB_NAME` if needed).

-- Set database defaults
ALTER DATABASE `YOUR_DB_NAME`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_polish_ci;

-- Convert Taskora tables
ALTER TABLE `taskora_projects` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `taskora_tasks`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;

-- Convert your users table (used by login/profile)
-- (Do this only if it is safe for your other apps that also use this table.)
ALTER TABLE `uzytkownicy` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;

-- If you have any other tables where you store Polish text, convert them too.

-- Optional: ensure typical text columns use utf8mb4 explicitly
-- (Run only if you need it.)
-- ALTER TABLE `uzytkownicy`
--   MODIFY `imie` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci,
--   MODIFY `nazwisko` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci,
--   MODIFY `nick` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci,
--   MODIFY `opis` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
