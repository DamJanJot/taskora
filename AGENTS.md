# AGENTS.md

Wytyczne dla agentow AI pracujacych w repozytorium `taskora`.

## 1. Cel

- Utrzymuj aplikacje Taskora (PHP + MySQL + JS) bez regresji w:
  - statusach taskow,
  - drag and drop,
  - kolejnosci taskow,
  - obsludze projektow.
- Preferuj male, bezpieczne zmiany i jasne komunikaty o ryzyku.

## 2. Stack i kluczowe obszary

- Backend: PHP 8, API w `api/`
- Frontend: `index.php`, `assets/js/taskora-board.js`, `assets/js/taskora-projects.js`
- DB config: `config/db.php`
- Migracje: `migrations/taskora_v3.sql`, `db_utf8mb4_fix.sql`
- Deploy: Render przez Docker (`Dockerfile`, `render.yaml`)

## 3. Zasady pracy na bazie danych

Uzywane tabele domenowe:

- `uzytkownicy`
- `taskora_projects`
- `taskora_tasks`

Wymagania kompatybilnosci:

- Statusy docelowe: `ready`, `progress`, `review`, `done`.
- Zachowaj zgodnosc ze starszymi danymi (`in_progress`) tam, gdzie kod ma fallback.
- `taskora_tasks.project_id` nie powinno byc `NULL` dla aktywnych rekordow.
- Kolejnosc taskow opiera sie o `sort_order`.

Przy zmianach DB:

1. Dodaj/uzupelnij migracje SQL.
2. Nie usuwaj fallbackow bez potwierdzenia, ze produkcja jest zmigrowana.
3. Przed destrukcyjnymi operacjami wymagaj backupu.

## 4. Standard zmian w kodzie

- Najpierw diagnoza przyczyny, potem fix.
- Nie zmieniaj niepowiazanych plikow.
- Zachowaj istniejace API i nazewnictwo, chyba ze zmiana jest celowa i opisana.
- Po zmianie endpointu API sprawdz:
  - walidacje wejscia,
  - autoryzacje po `user_id`,
  - format JSON odpowiedzi,
  - obsluge bledow.
- Dla drag and drop i statusow testuj scenariusze po odswiezeniu strony.

## 5. Checklista po zmianach

Minimum do sprawdzenia:

1. Dodanie taska do `progress` dziala.
2. Przenoszenie taska miedzy kolumnami zapisuje sie po reloadzie.
3. Zmiana kolejnosci taskow w kolumnie zapisuje sie po reloadzie.
4. Tworzenie projektu nie duplikuje rekordu.
5. Edycja projektu dziala i utrzymuje dane po reloadzie.

Dodatkowo:

- Lint PHP dla zmienionych plikow (`php -l <plik>`).
- Brak przypadkowych artefaktow w commit (zipy, foldery tymczasowe).

## 6. Deploy i demo

- Render ma dzialac jako `Docker`, nie `Node`.
- Jezeli logi pokazuja `yarn start` lub brak `package.json`, to runtime jest zly.
- Wymagane env vars na Render:
  - `DB_HOST`
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASS`

## 7. Dokumentacja i komunikacja

- Aktualizuj `README.md`, gdy zmienia sie setup, deploy albo flow aplikacji.
- W opisie PR/commita podawaj:
  - co bylo przyczyna,
  - co zostalo zmienione,
  - jak przetestowano,
  - czy potrzebna jest migracja.

## 8. Czego nie robic bez zgody

- Nie usuwaj masowo danych produkcyjnych.
- Nie zmieniaj hasel, sekretow i konfiguracji srodowiska poza uzgodnionym zakresem.
- Nie usuwaj fallbackow kompatybilnosci ze starsza baza bez planu migracji.
