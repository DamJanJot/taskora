# Taskora – Kanban Task Board

Taskora is a web-based Kanban board application for managing tasks efficiently.

## Features
- User authentication (based on the `uzytkownicy` table)
- Add new tasks
- Edit and delete tasks
- Drag & drop between columns:
  - Task Ready
  - In Progress
  - Needs Review
  - Done
- Clean and responsive interface
- Task history stored in the database

## Database Structure
Table `taskora_projects`:
```sql
CREATE TABLE taskora_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('ready','progress','review','done') DEFAULT 'ready',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES uzytkownicy(id)
);
```

## Technologies
- **PHP 8** (backend + API)
- **MySQL** (database)
- **JavaScript (Fetch + SortableJS)** (interactivity + AJAX)
- **HTML + CSS** (frontend)

## Installation
1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/taskora.git
   ```
2. Configure your database in `config/db.php`.
3. Import the `taskora_projects` table (SQL above).
4. Launch the application in your browser.

## Demo
After logging in, each user sees their own Kanban board and can manage tasks in real time.
