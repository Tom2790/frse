<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Metadane paginacji doklada sam Laravel. Przez with() dodajemy tylko globalny licznik
 * przypietych, ktorego framework nie zna, a ktory jest potrzebny w naglowku widgetu.
 *
 * Wlasny klucz meta w toArray() bylby bledem: Laravel scala go przez array_merge_recursive
 * i powtorzone wartosci robia sie tablicami ("total": [3, 3]).
 */
class NoteResourceCollection extends ResourceCollection
{
    /** @var class-string<NoteResource> */
    public $collects = NoteResource::class;

    /**
     * @param  mixed  $resource
     * @param  int|null  $pinnedTotal Wszystkie przypiete notatki uzytkownika, nie tylko z tej strony.
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
