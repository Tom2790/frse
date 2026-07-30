# laravel-notes

Aplikacja Laravel 13 realizująca Zadania **1, 2, 4, 5a i 5b** z zestawu rekrutacyjnego.

Opis, mapa zadań → pliki i instrukcja uruchomienia są w [README głównego
repozytorium](../README.md). Dokumentacja szczegółowa:

- [`docs/zadanie-2-refaktoryzacja.md`](docs/zadanie-2-refaktoryzacja.md) — Repository + Service Layer, kod przed/po
- [`docs/zadanie-5b-dlaczego-shouldqueue.md`](docs/zadanie-5b-dlaczego-shouldqueue.md) — dlaczego listener jest kolejkowany

Zadanie 3 (kolejka asynchroniczna w czystym JS) jest w katalogu
[`../zadanie-3-task-queue`](../zadanie-3-task-queue).

## Skrót uruchomienia

```bash
composer install && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
npm install && npm run build
php artisan serve            # http://127.0.0.1:8000
php artisan queue:work       # drugi terminal (Zadanie 5b)
php artisan test             # 49 testów
```

Logowanie: `demo@example.com` / `password`.
