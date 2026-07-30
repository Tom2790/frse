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
 * API powiadomień dla komponentu `NotificationBell.vue` (Zadanie 5a).
 *
 * Ten sam podział warstw co w notatkach: kontroler tłumaczy HTTP, serwis zna reguły
 * (limit 20 najnowszych, oznaczanie jako przeczytane), a izolację danych wymusza
 * zapytanie zawężone do właściciela.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * GET /api/notifications — 20 najnowszych powiadomień + licznik nieprzeczytanych.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return NotificationResource::collection($this->notifications->latestFor($user))
            ->additional(['meta' => ['unread_count' => $this->notifications->unreadCount($user)]])
            ->response();
    }

    /**
     * PATCH /api/notifications/{notification}/read — oznacz jedno jako przeczytane.
     */
    public function read(Request $request, int $notification): JsonResponse
    {
        $user = $this->user($request);
        $model = $this->notifications->markAsRead($notification, $user);

        return NotificationResource::make($model)
            ->additional(['meta' => ['unread_count' => $this->notifications->unreadCount($user)]])
            ->response();
    }

    /**
     * PATCH /api/notifications/read-all — oznacz wszystkie jako przeczytane.
     */
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
        $user = $request->user();

        assert($user instanceof User);

        return $user;
    }
}
