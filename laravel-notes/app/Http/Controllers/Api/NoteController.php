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
 * CRUD notatek. Kontroler tlumaczy HTTP na wywolania NoteService i pilnuje autoryzacji.
 * Logika biznesowa jest w serwisie, dostep do danych w repozytorium.
 */
class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $notes,
    ) {}

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

    public function show(Request $request, int $note): NoteResource
    {
        $model = $this->notes->findOrFail($note, $this->user($request));

        Gate::authorize('view', $model);

        return NoteResource::make($model);
    }

    /** Obsluguje tez aktualizacje czesciowa, np. samo is_pinned z widgetu. */
    public function update(UpdateNoteRequest $request, int $note): NoteResource
    {
        $user = $this->user($request);

        // Wczytujemy notatke, zeby polityka mogla ja ocenic. Serwis i tak sprawdza
        // wlasciciela drugi raz - to jedno dodatkowe zapytanie za jasny podzial warstw.
        Gate::authorize('update', $this->notes->findOrFail($note, $user));

        return NoteResource::make(
            $this->notes->update($note, $request->toServicePayload(), $user),
        );
    }

    public function destroy(Request $request, int $note): Response
    {
        $user = $this->user($request);

        Gate::authorize('delete', $this->notes->findOrFail($note, $user));

        $this->notes->delete($note, $user);

        return response()->noContent();
    }

    /**
     * Trasy stoja za auth:sanctum, wiec uzytkownik zawsze jest. Adnotacja jest dla
     * analizy statycznej, zeby nie zgadywala, ze moze przyjsc null.
     */
    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
