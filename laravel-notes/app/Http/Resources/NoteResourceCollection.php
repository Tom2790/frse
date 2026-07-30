<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Kolekcja notatek — jeden, przewidywalny kształt listy dla całego API.
 *
 * Standardowe metadane paginacji (`current_page`, `per_page`, `last_page`, `total`, …)
 * dokłada sam Laravel. My dodajemy przez `with()` tylko to, czego framework nie zna:
 * globalny licznik przypiętych notatek dla nagłówka karty w `NoteManager.vue`.
 *
 * Uwaga na pułapkę: gdyby `toArray()` zwracało własny klucz `meta`, Laravel scaliłby go
 * z własnymi metadanymi przez `array_merge_recursive` i każda powtórzona wartość
 * zamieniłaby się w tablicę (`"total": [3, 3]`).
 */
class NoteResourceCollection extends ResourceCollection
{
    /**
     * Element kolekcji.
     *
     * @var class-string<NoteResource>
     */
    public $collects = NoteResource::class;

    /**
     * @param  mixed  $resource
     * @param  int|null  $pinnedTotal Globalna liczba przypiętych notatek użytkownika
     *                                — nie tylko z bieżącej strony.
     */
    public function __construct($resource, private readonly ?int $pinnedTotal = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map->toArray($request)->all();
    }

    /**
     * Dodatkowe metadane doklejane obok metadanych paginacji.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        if ($this->pinnedTotal === null) {
            return [];
        }

        return ['meta' => ['pinned_total' => $this->pinnedTotal]];
    }
}
