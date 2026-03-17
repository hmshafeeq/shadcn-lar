<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationPreference;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('can create notification preference', function () {
    $user = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $user->id,
        'category' => NotificationCategory::MARKETING,
        'channel' => NotificationChannel::EMAIL,
        'enabled' => true,
    ]);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
        'category' => 'marketing',
        'channel' => 'email',
        'enabled' => true,
    ]);
});

test('casts category to enum', function () {
    $preference = NotificationPreference::factory()->create([
        'category' => NotificationCategory::SECURITY,
    ]);

    expect($preference->category)->toBeInstanceOf(NotificationCategory::class);
    expect($preference->category)->toBe(NotificationCategory::SECURITY);
});

test('casts channel to enum', function () {
    $preference = NotificationPreference::factory()->create([
        'channel' => NotificationChannel::DATABASE,
    ]);

    expect($preference->channel)->toBeInstanceOf(NotificationChannel::class);
    expect($preference->channel)->toBe(NotificationChannel::DATABASE);
});

test('casts enabled to boolean', function () {
    $preference = NotificationPreference::factory()->create([
        'enabled' => 1,
    ]);

    expect($preference->enabled)->toBeBool()->toBeTrue();
});

test('belongs to user', function () {
    $user = User::factory()->create();
    $preference = NotificationPreference::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($preference->user)->toBeInstanceOf(User::class);
    expect($preference->user->id)->toBe($user->id);
});

test('factory creates valid preference', function () {
    $preference = NotificationPreference::factory()->create();

    expect($preference->id)->not->toBeNull();
    expect($preference->user_id)->not->toBeNull();
    expect($preference->category)->toBeInstanceOf(NotificationCategory::class);
    expect($preference->channel)->toBeInstanceOf(NotificationChannel::class);
    expect($preference->enabled)->toBeBool();
});

test('factory enabled state', function () {
    $preference = NotificationPreference::factory()->enabled()->create();

    expect($preference->enabled)->toBeTrue();
});

test('factory disabled state', function () {
    $preference = NotificationPreference::factory()->disabled()->create();

    expect($preference->enabled)->toBeFalse();
});

test('factory for category state', function () {
    $preference = NotificationPreference::factory()
        ->forCategory(NotificationCategory::TRANSACTIONAL)
        ->create();

    expect($preference->category)->toBe(NotificationCategory::TRANSACTIONAL);
});

test('factory for channel state', function () {
    $preference = NotificationPreference::factory()
        ->forChannel(NotificationChannel::PUSH)
        ->create();

    expect($preference->channel)->toBe(NotificationChannel::PUSH);
});

test('unique constraint on user category channel', function () {
    $user = User::factory()->create();

    NotificationPreference::create([
        'user_id' => $user->id,
        'category' => NotificationCategory::MARKETING,
        'channel' => NotificationChannel::EMAIL,
        'enabled' => true,
    ]);

    expect(fn () => NotificationPreference::create([
        'user_id' => $user->id,
        'category' => NotificationCategory::MARKETING,
        'channel' => NotificationChannel::EMAIL,
        'enabled' => false,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
