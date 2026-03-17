<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Notifications\GenericNotification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
});

test('user can view notifications page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('dashboard.notifications.index'));

    $response->assertStatus(200);
});

test('user can get unread count', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('dashboard.notifications.unread-count'));

    $response->assertStatus(200);
    $response->assertJsonStructure(['count']);
});

test('user can mark notification as read', function () {
    Notification::send($this->user, new GenericNotification(
        title: 'Test',
        message: 'Test',
        category: NotificationCategory::SYSTEM,
    ));

    $notification = $this->user->notifications()->first();

    $response = $this->actingAs($this->user)
        ->postJson(route('dashboard.notifications.read', $notification->id));

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->notifications()->first()->read_at)->not->toBeNull();
});

test('user can mark all notifications as read', function () {
    Notification::send($this->user, new GenericNotification(
        title: 'Test 1',
        message: 'Test',
        category: NotificationCategory::SYSTEM,
    ));

    Notification::send($this->user, new GenericNotification(
        title: 'Test 2',
        message: 'Test',
        category: NotificationCategory::MARKETING,
    ));

    $response = $this->actingAs($this->user)
        ->postJson(route('dashboard.notifications.read-all'));

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('user can delete notification', function () {
    Notification::send($this->user, new GenericNotification(
        title: 'Test',
        message: 'Test',
        category: NotificationCategory::SYSTEM,
    ));

    $notification = $this->user->notifications()->first();

    $response = $this->actingAs($this->user)
        ->deleteJson(route('dashboard.notifications.destroy', $notification->id));

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->notifications()->count())->toBe(0);
});

test('user cannot access other users notifications', function () {
    $otherUser = User::factory()->create();

    Notification::send($otherUser, new GenericNotification(
        title: 'Private',
        message: 'Private message',
        category: NotificationCategory::SECURITY,
    ));

    $notification = $otherUser->notifications()->first();

    $response = $this->actingAs($this->user)
        ->postJson(route('dashboard.notifications.read', $notification->id));

    $response->assertStatus(403);
});

test('unauthenticated user cannot access notifications', function () {
    $response = $this->getJson(route('dashboard.notifications.unread-count'));

    $response->assertStatus(401);
});
