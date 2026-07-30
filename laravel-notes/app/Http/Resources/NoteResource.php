<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
     * Podmiana domyslnej AnonymousResourceCollection na nasza klase, zeby
     * NoteResource::collection() i new NoteResourceCollection() dawaly to samo JSON-owo.
     *
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): NoteResourceCollection
    {
        return new NoteResourceCollection($resource);
    }
}
