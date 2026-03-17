<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationTemplate;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('can create notification template', function () {
    NotificationTemplate::create([
        'name' => 'Welcome Email',
        'subject' => 'Welcome to {{ app_name }}',
        'body' => 'Hello {{ user_name }}, welcome!',
        'category' => NotificationCategory::COMMUNICATION,
        'channels' => [NotificationChannel::DATABASE->value, NotificationChannel::EMAIL->value],
        'variables' => ['app_name', 'user_name'],
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('notification_templates', [
        'name' => 'Welcome Email',
        'category' => 'communication',
    ]);
});

test('generates slug automatically', function () {
    $template = NotificationTemplate::create([
        'name' => 'Order Confirmation Email',
        'subject' => 'Order Confirmed',
        'body' => 'Your order is confirmed.',
        'category' => NotificationCategory::TRANSACTIONAL,
        'channels' => [NotificationChannel::EMAIL->value],
    ]);

    expect($template->slug)->toBe('order-confirmation-email');
});

test('generates unique slug when duplicate', function () {
    NotificationTemplate::create([
        'name' => 'Test Template',
        'subject' => 'Test',
        'body' => 'Test body',
        'category' => NotificationCategory::SYSTEM,
        'channels' => [NotificationChannel::DATABASE->value],
    ]);

    $template2 = NotificationTemplate::create([
        'name' => 'Test Template',
        'subject' => 'Test 2',
        'body' => 'Test body 2',
        'category' => NotificationCategory::SYSTEM,
        'channels' => [NotificationChannel::DATABASE->value],
    ]);

    expect($template2->slug)->toBe('test-template-1');
});

test('casts category to enum', function () {
    $template = NotificationTemplate::factory()->create([
        'category' => NotificationCategory::SECURITY,
    ]);

    expect($template->category)->toBeInstanceOf(NotificationCategory::class);
});

test('casts channels to array', function () {
    $template = NotificationTemplate::factory()->create([
        'channels' => ['database', 'email'],
    ]);

    expect($template->channels)->toBeArray()->toHaveCount(2);
});

test('casts variables to array', function () {
    $template = NotificationTemplate::factory()->create([
        'variables' => ['user_name', 'order_id'],
    ]);

    expect($template->variables)->toBeArray();
});

test('scope active', function () {
    NotificationTemplate::factory()->create(['is_active' => true]);
    NotificationTemplate::factory()->create(['is_active' => false]);

    $activeTemplates = NotificationTemplate::active()->get();

    expect($activeTemplates)->toHaveCount(1);
    expect($activeTemplates->first()->is_active)->toBeTrue();
});

test('scope by category', function () {
    NotificationTemplate::factory()->create(['category' => NotificationCategory::SECURITY]);
    NotificationTemplate::factory()->create(['category' => NotificationCategory::MARKETING]);

    $securityTemplates = NotificationTemplate::byCategory(NotificationCategory::SECURITY)->get();

    expect($securityTemplates)->toHaveCount(1);
    expect($securityTemplates->first()->category)->toBe(NotificationCategory::SECURITY);
});

test('scope by channel', function () {
    NotificationTemplate::factory()->create(['channels' => ['database']]);
    NotificationTemplate::factory()->create(['channels' => ['email']]);

    $databaseTemplates = NotificationTemplate::byChannel(NotificationChannel::DATABASE)->get();

    expect($databaseTemplates)->toHaveCount(1);
});

test('render replaces variables', function () {
    $template = NotificationTemplate::factory()->create([
        'subject' => 'Hello {{ user_name }}',
        'body' => 'Welcome to {{ app_name }}, {{ user_name }}!',
    ]);

    $rendered = $template->render([
        'user_name' => 'John',
        'app_name' => 'MyApp',
    ]);

    expect($rendered['subject'])->toBe('Hello John');
    expect($rendered['body'])->toBe('Welcome to MyApp, John!');
});

test('supports channel returns true for supported', function () {
    $template = NotificationTemplate::factory()->create([
        'channels' => ['database', 'email'],
    ]);

    expect($template->supportsChannel(NotificationChannel::DATABASE))->toBeTrue();
    expect($template->supportsChannel(NotificationChannel::EMAIL))->toBeTrue();
    expect($template->supportsChannel('database'))->toBeTrue();
});

test('supports channel returns false for unsupported', function () {
    $template = NotificationTemplate::factory()->create([
        'channels' => ['database'],
    ]);

    expect($template->supportsChannel(NotificationChannel::SMS))->toBeFalse();
    expect($template->supportsChannel('push'))->toBeFalse();
});

test('soft deletes', function () {
    $template = NotificationTemplate::factory()->create();
    $templateId = $template->id;

    $template->delete();

    $this->assertSoftDeleted('notification_templates', ['id' => $templateId]);
    expect(NotificationTemplate::find($templateId))->toBeNull();
    expect(NotificationTemplate::withTrashed()->find($templateId))->not->toBeNull();
});
