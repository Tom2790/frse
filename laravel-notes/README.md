# laravel-notes

Aplikacja Laravel 13 realizująca zadania 1, 2, 4, 5a i 5b.
Opis całości i instrukcja uruchomienia znajdują się w katalogu głównym repozytorium.

Zadanie 3 (kolejka asynchroniczna w JS) jest w katalogu `zadanie-3-task-queue`.

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
