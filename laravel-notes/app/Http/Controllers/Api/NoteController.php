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
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD notatek.
 *
 * Kontroler jest cienki i celowo nie zna Eloquenta: tłumaczy HTTP na wywołania
 * `NoteService`, pilnuje autoryzacji politykami i pakuje wynik w zasoby JSON.
 * Całą logikę biznesową (limit, wartości domyślne, zdarzenia) ma serwis,
 * a dostęp do danych — repozytorium.
 */
class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $notes,
    ) {}

    /**
     * GET /api/notes — stronicowana lista notatek zalogowanego użytkownika.
     */
    public function index(Request $request): NoteResourceCollection
    {
        Gate::authorize('viewAny', Note::class);

        $user = $this->user($request);
        $perPage = $request->integer('per_page') ?: null;

        return new NoteResourceCollection(
            $this->notes->paginate($user, $perPage),
            $this->notes->countPinned($user),
        );
    }

    /**
     * POST /api/notes — nowa notatka. 201 + Location na utworzony zasób.
     */
    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $this->notes->create(
            $request->toServicePayload(),
            $this->user($request),
        );

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
        $model = $this->notes->findOrFail($note, $this->user($request));

        Gate::authorize('view', $model);

        return NoteResource::make($model);
    }

    /**
     * PUT|PATCH /api/notes/{note} — aktualizacja (także częściowa, np. samo `is_pinned`).
     */
    public function update(UpdateNoteRequest $request, int $note): NoteResource
    {
        $user = $this->user($request);

        Gate::authorize('update', $this->notes->findOrFail($note, $user));

        return NoteResource::make(
            $this->notes->update($note, $request->toServicePayload(), $user),
        );
    }

    /**
     * DELETE /api/notes/{note} — usunięcie. 204 bez treści.
     */
    public function destroy(Request $request, int $note): Response
    {
        $user = $this->user($request);

        Gate::authorize('delete', $this->notes->findOrFail($note, $user));

        $this->notes->delete($note, $user);

        return response()->noContent();
    }

    /**
     * Uwierzytelniony użytkownik z gwarancją typu — trasy są za `auth:sanctum`,
     * więc `null` w tym miejscu oznaczałoby błąd konfiguracji, nie sytuację runtime.
     */
    private function user(Request $request): User
    {
        $user = $request->user();

        assert($user instanceof User);

        return $user;
    }
}
