<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * API powiadomień dla komponentu dzwonka (Zadanie 5a).
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function zwraca_powiadomienia_uzytkownika_z_licznikiem_nieprzeczytanych(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->unread()->for($user)->create();
        Notification::factory()->count(2)->read()->for($user)->create();
        Notification::factory()->count(4)->unread()->for(User::factory()->create())->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.unread_count', 3)
            ->assertJsonStructure(['data' => [['id', 'type', 'title', 'body', 'read_at', 'created_at']]]);
    }

    #[Test]
    public function zwraca_maksymalnie_20_najnowszych(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(25)->unread()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(NotificationService::FEED_LIMIT, 'data')
            // Licznik nieprzeczytanych jest globalny, nie ograniczony do 20.
            ->assertJsonPath('meta.unread_count', 25);

        $newest = Notification::query()->where('user_id', $user->id)
            ->orderByDesc('created_at')->orderByDesc('id')->first();

        $this->assertSame($newest?->id, $response->json('data.0.id'));
    }

    #[Test]
    public function oznacza_jedno_powiadomienie_jako_przeczytane(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->unread()->for($user)->create();
        Notification::factory()->count(2)->unread()->for($user)->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('meta.unread_count', 2);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    #[Test]
    public function powtorne_oznaczenie_nie_zmienia_daty_przeczytania(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->unread()->for($user)->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/notifications/{$notification->id}/read")->assertOk();
        $firstReadAt = $notification->refresh()->read_at;

        $this->patchJson("/api/notifications/{$notification->id}/read")->assertOk();

        $this->assertEquals($firstReadAt, $notification->refresh()->read_at);
    }

    #[Test]
    public function nie_mozna_oznaczyc_cudzego_powiadomienia(): void
    {
        $user = User::factory()->create();
        $foreign = Notification::factory()->unread()->for(User::factory()->create())->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/notifications/{$foreign->id}/read")->assertNotFound();

        $this->assertNull($foreign->refresh()->read_at);
    }

    #[Test]
    public function oznacza_wszystkie_jako_przeczytane_tylko_dla_wlasciciela(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Notification::factory()->count(4)->unread()->for($user)->create();
        Notification::factory()->count(3)->unread()->for($other)->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('meta.marked', 4)
            ->assertJsonPath('meta.unread_count', 0);

        $this->assertSame(0, Notification::query()->where('user_id', $user->id)->whereNull('read_at')->count());
        $this->assertSame(3, Notification::query()->where('user_id', $other->id)->whereNull('read_at')->count());
    }

    #[Test]
    public function endpointy_wymagaja_uwierzytelnienia(): void
    {
        $notification = Notification::factory()->for(User::factory()->create())->create();

        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->patchJson("/api/notifications/{$notification->id}/read")->assertUnauthorized();
        $this->patchJson('/api/notifications/read-all')->assertUnauthorized();
    }
}
