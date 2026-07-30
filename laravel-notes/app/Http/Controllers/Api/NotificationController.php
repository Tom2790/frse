<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API dla komponentu NotificationBell.vue.
 *
 * Licznik nieprzeczytanych wraca w meta.unread_count przy kazdej odpowiedzi, bo lista
 * jest ucieta do 20 pozycji - front nie policzylby go poprawnie sam.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return NotificationResource::collection($this->notifications->latestFor($user))
            ->additional(['meta' => ['unread_count' => $this->notifications->unreadCount($user)]])
            ->response();
    }

    public function read(Request $request, int $notification): JsonResponse
    {
        $user = $this->user($request);
        $model = $this->notifications->markAsRead($notification, $user);

        return NotificationResource::make($model)
            ->additional(['meta' => ['unread_count' => $this->notifications->unreadCount($user)]])
            ->response();
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'message' => 'Wszystkie powiadomienia oznaczono jako przeczytane.',
            'meta' => [
                'marked' => $this->notifications->markAllAsRead($user),
                'unread_count' => 0,
            ],
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
