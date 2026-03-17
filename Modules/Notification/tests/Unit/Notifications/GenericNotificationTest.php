<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Notifications\GenericNotification;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('can create generic notification', function () {
    $notification = new GenericNotification(
        title: 'Test Title',
        message: 'Test message',
        category: NotificationCategory::SYSTEM,
    );

    expect($notification)->toBeInstanceOf(GenericNotification::class);
});

test('via returns correct channels', function () {
    $notification = new GenericNotification(
        title: 'Test',
        message: 'Test',
        category: NotificationCategory::SYSTEM,
        channels: [NotificationChannel::DATABASE, NotificationChannel::EMAIL],
    );

    $user = User::factory()->create();
    $channels = $notification->via($user);

    expect($channels)->toContain('database')->toContain('mail');
});

test('to array returns correct structure', function () {
    $notification = new GenericNotification(
        title: 'Test Title',
        message: 'Test message',
        category: NotificationCategory::MARKETING,
        actionUrl: 'https://example.com',
        actionLabel: 'Click here',
        icon: 'sparkles',
    );

    $user = User::factory()->create();
    $array = $notification->toArray($user);

    expect($array['title'])->toBe('Test Title');
    expect($array['message'])->toBe('Test message');
    expect($array['category'])->toBe('marketing');
    expect($array['action_url'])->toBe('https://example.com');
    expect($array['action_label'])->toBe('Click here');
    expect($array['icon'])->toBe('sparkles');
});

test('to mail returns mail message', function () {
    $notification = new GenericNotification(
        title: 'Test Title',
        message: 'Test message',
        category: NotificationCategory::COMMUNICATION,
        actionUrl: 'https://example.com',
        actionLabel: 'View',
    );

    $user = User::factory()->create();
    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class);
});

test('from template creates notification', function () {
    $template = NotificationTemplate::factory()->create([
        'subject' => 'Hello {{ user_name }}',
        'body' => 'Welcome {{ user_name }}!',
        'category' => NotificationCategory::COMMUNICATION,
        'channels' => ['database', 'email'],
    ]);

    $notification = GenericNotification::fromTemplate(
        template: $template,
        variables: ['user_name' => 'John'],
        actionUrl: 'https://example.com',
    );

    $user = User::factory()->create();
    $array = $notification->toArray($user);

    expect($array['title'])->toBe('Hello John');
    expect($array['message'])->toBe('Welcome John!');
});

test('defaults to database channel', function () {
    $notification = new GenericNotification(
        title: 'Test',
        message: 'Test',
        category: NotificationCategory::SYSTEM,
    );

    $user = User::factory()->create();
    $channels = $notification->via($user);

    expect($channels)->toContain('database');
});

test('uses category icon when not provided', function () {
    $notification = new GenericNotification(
        title: 'Test',
        message: 'Test',
        category: NotificationCategory::SECURITY,
    );

    $user = User::factory()->create();
    $array = $notification->toArray($user);

    expect($array['icon'])->toBe(NotificationCategory::SECURITY->icon());
});

test('to database returns same as to array', function () {
    $notification = new GenericNotification(
        title: 'Test',
        message: 'Test',
        category: NotificationCategory::TRANSACTIONAL,
    );

    $user = User::factory()->create();

    expect($notification->toDatabase($user))->toBe($notification->toArray($user));
});
