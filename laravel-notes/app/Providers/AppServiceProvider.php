<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\NoteRepositoryInterface;
use App\Repositories\EloquentNoteRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Mapa interfejs → implementacja (skrótowa forma `$this->app->bind()`).
     *
     * To jedyne miejsce, w którym aplikacja decyduje, *czym* jest repozytorium notatek.
     * Podmiana źródła danych (np. na `ApiNoteRepository` albo atrapę w testach)
     * to zmiana jednej linii — pozostały kod zna wyłącznie interfejs.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        NoteRepositoryInterface::class => EloquentNoteRepository::class,
    ];

    public function register(): void
    {
        // Równoważny, jawny zapis wiązania powyżej — wymagany treścią Zadania 2.
        // Wystarczy jedna z tych dwóch form; właściwość `$bindings` jest wydajniejsza,
        // bo kontener czyta ją bez uruchamiania metody.
        $this->app->bind(NoteRepositoryInterface::class, EloquentNoteRepository::class);
    }

    public function boot(): void
    {
        // Poza produkcją traktujemy leniwe ładowanie relacji (N+1) jako błąd,
        // nie jako drobiazg do znalezienia kiedyś na produkcji.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
