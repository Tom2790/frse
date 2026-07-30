<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Http\Resources\NoteResourceCollection;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD notatek (Zadanie 1).
 *
 * Na tym etapie kontroler rozmawia z Eloquentem bezpośrednio. Zadanie 2 wprowadza
 * warstwę repozytorium i serwisu, do której ta logika zostanie przeniesiona.
 */
class NoteController extends Controller
{
    /** Domyślny rozmiar strony wymagany w specyfikacji. */
    private const int PER_PAGE = 15;

    /**
     * GET /api/notes — stronicowana lista notatek zalogowanego użytkownika.
     */
    public function index(Request $request): NoteResourceCollection
    {
        Gate::authorize('viewAny', Note::class);

        $user = $this->user($request);

        $notes = Note::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $pinnedTotal = Note::query()
            ->where('user_id', $user->id)
            ->where('is_pinned', true)
            ->count();

        return new NoteResourceCollection($notes, $pinnedTotal);
    }

    /**
     * POST /api/notes — nowa notatka. 201 + Location na utworzony zasób.
     */
    public function store(StoreNoteRequest $request): JsonResponse
    {
        // `user_id` celowo nie jest na liście `#[Fillable]` modelu, więc właściciela
        // przypisujemy relacją — nie da się go podstawić z ciała żądania.
        $note = new Note([
            'title' => $request->string('title')->toString(),
            'content' => $request->string('content')->toString(),
            'is_pinned' => $request->boolean('is_pinned'),
        ]);

        $note->user()->associate($this->user($request));
        $note->save();

        return NoteResource::make($note)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('api.notes.show', $note));
    }

    /**
     * GET /api/notes/{note} — pojedyncza notatka.
     */
    public function show(Request $request, int $note): NoteResource
    {
        $model = $this->findOwnedOrFail($request, $note);

        Gate::authorize('view', $model);

        return NoteResource::make($model);
    }

    /**
     * PUT|PATCH /api/notes/{note} — aktualizacja (także częściowa, np. samo `is_pinned`).
     */
    public function update(UpdateNoteRequest $request, int $note): NoteResource
    {
        $model = $this->findOwnedOrFail($request, $note);

        Gate::authorize('update', $model);

        $model->fill($request->safe()->only(['title', 'content', 'is_pinned']))->save();

        return NoteResource::make($model);
    }

    /**
     * DELETE /api/notes/{note} — usunięcie. 204 bez treści.
     */
    public function destroy(Request $request, int $note): Response
    {
        $model = $this->findOwnedOrFail($request, $note);

        Gate::authorize('delete', $model);

        $model->delete();

        return response()->noContent();
    }

    /**
     * Zapytanie zawężone do właściciela — fundament izolacji danych.
     * Cudza notatka kończy się jako 404 (nie 403): nie potwierdzamy jej istnienia.
     */
    private function findOwnedOrFail(Request $request, int $id): Note
    {
        return Note::query()
            ->where('user_id', $this->user($request)->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * Uwierzytelniony użytkownik z gwarancją typu — trasy są za `auth:sanctum`,
     * więc `null` oznaczałoby błąd konfiguracji, nie sytuację runtime.
     */
    private function user(Request $request): User
    {
        $user = $request->user();

        assert($user instanceof User);

        return $user;
    }
}
