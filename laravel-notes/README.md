# laravel-notes

Aplikacja Laravel 13 realizująca zadania 1, 2, 4, 5a i 5b.

Opis całości i instrukcja uruchomienia: [README repozytorium](../README.md).

Dokumentacja szczegółowa:

- [`docs/zadanie-2-refaktoryzacja.md`](docs/zadanie-2-refaktoryzacja.md) - Repository + Service Layer, kod przed i po
- [`docs/zadanie-5b-dlaczego-shouldqueue.md`](docs/zadanie-5b-dlaczego-shouldqueue.md) - dlaczego listener jest kolejkowany

Zadanie 3 (kolejka asynchroniczna w JS) jest w [`../zadanie-3-task-queue`](../zadanie-3-task-queue).

## Skrót

```bash
composer install && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
npm install && npm run build
php artisan serve            # http://127.0.0.1:8000
php artisan queue:work       # drugi terminal, zadanie 5b
php artisan test             # backend, 55 testów
npm test                     # front, 45 testów
```

Logowanie: `tomek-remlein@wp.pl` / `password`.
