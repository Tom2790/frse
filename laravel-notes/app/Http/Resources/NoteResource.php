<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reprezentacja jednej notatki w API.
 *
 * @mixin Note
 */
class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'is_pinned' => $this->is_pinned,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Podmienia domyślną `AnonymousResourceCollection` na naszą klasę kolekcji.
     * Dzięki temu `NoteResource::collection($notes)` i `new NoteResourceCollection($notes)`
     * dają dokładnie ten sam kształt odpowiedzi — nie ma dwóch formatów listy notatek.
     *
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): NoteResourceCollection
    {
        return new NoteResourceCollection($resource);
    }
}
