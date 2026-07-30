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
     * Jedyne miejsce, w ktorym aplikacja decyduje, czym jest repozytorium notatek.
     * Podmiana zrodla danych to zmiana jednej linii.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        NoteRepositoryInterface::class => EloquentNoteRepository::class,
    ];

    public function register(): void
    {
        // Ten sam binding zapisany jawnie, tak jak wymaga tresc zadania 2. Wystarczy
        // jedna z dwoch form - wlasciwosc $bindings jest tansza, bo kontener czyta ja
        // bez uruchamiania metody.
        $this->app->bind(NoteRepositoryInterface::class, EloquentNoteRepository::class);
    }

    public function boot(): void
    {
        // Poza produkcja leniwe ladowanie relacji (N+1) ma wywalac blad od razu.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
