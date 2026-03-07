# Taskora - Kanban Task Board

Taskora to webowa aplikacja Kanban do zarzadzania taskami i projektami.

## Linki
- GitHub: https://github.com/DamJanJot/taskora
- Demo (Render): https://taskora-ae4p.onrender.com

## Funkcje
- Logowanie uzytkownika (`uzytkownicy`)
- Lista projektow (`taskora_projects`)
- Tablica taskow per projekt (`taskora_tasks`)
- Drag and drop miedzy kolumnami
- Trwala kolejnosc taskow w kolumnie
- Dodawanie, edycja i usuwanie projektow
- Dodawanie, edycja i usuwanie taskow

## Stack
- PHP 8 + Apache
- MySQL
- JavaScript (Fetch + SortableJS)
- HTML + CSS

## Lokalny start
1. Sklonuj repozytorium:
  ```bash
  git clone https://github.com/DamJanJot/taskora.git
  cd taskora
  ```
2. Skopiuj zmienne srodowiskowe:
  ```bash
  cp .env.example .env
  ```
3. Uzupelnij dane DB w `.env` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
4. Uruchom SQL:
  - `taskora.sql`
  - `migrations/taskora_v3.sql`
5. Odpal przez Apache/PHP (np. XAMPP) i wejdz na `login.php`.

## Deploy na Render (PHP, bez Node)
Repo ma gotowe pliki:
- `Dockerfile`
- `render.yaml`

Kroki:
1. W Render wybierz `New +` -> `Web Service` -> repo `DamJanJot/taskora`.
2. Runtime ustaw na `Docker` (nie `Node`).
3. Dodaj env vars:
  - `DB_HOST`
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASS`
4. Deploy.

Jesli widzisz blad typu `yarn start` / `Couldn't find a package.json file`, to serwis jest ustawiony jako Node. Przelacz runtime na Docker lub utworz nowy Web Service z Docker.

## Baza danych (uzywane tabele)
- `uzytkownicy`
- `taskora_projects`
- `taskora_tasks`

## Taskora v3
1. Uruchom migracje: `migrations/taskora_v3.sql` (backup przed uruchomieniem).
2. Wejdz na `index.php` - najpierw lista projektow, potem tablica wybranego projektu.
3. Opisy wspieraja nowa linie, pogrubienie i listy.
4. Edycja taska: dwuklik na karcie lub ikona olowka.
