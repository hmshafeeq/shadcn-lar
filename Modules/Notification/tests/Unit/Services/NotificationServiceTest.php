<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Notifications\GenericNotification;
use Modules\Notification\Services\NotificationService;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new NotificationService;
});

test('send to user', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->service->sendToUser(
        user: $user,
        title: 'Test Notification',
        message: 'This is a test',
        category: NotificationCategory::SYSTEM,
    );

    Notification::assertSentTo($user, GenericNotification::class);
});

test('send to users', function () {
    Notification::fake();

    $users = User::factory()->count(3)->create();

    $this->service->sendToUsers(
        users: $users,
        title: 'Bulk Notification',
        message: 'Sent to all',
        category: NotificationCategory::MARKETING,
    );

    Notification::assertSentTo($users, GenericNotification::class);
});

test('send to role', function () {
    Notification::fake();

    $role = Role::create(['name' => 'admin']);
    $adminUser = User::factory()->create();
    $adminUser->assignRole($role);

    $regularUser = User::factory()->create();

    $this->service->sendToRole(
        role: 'admin',
        title: 'Admin Only',
        message: 'For admins',
        category: NotificationCategory::SYSTEM,
    );

    Notification::assertSentTo($adminUser, GenericNotification::class);
    Notification::assertNotSentTo($regularUser, GenericNotification::class);
});

test('broadcast', function () {
    Notification::fake();

    $users = User::factory()->count(5)->create();

    $this->service->broadcast(
        title: 'Broadcast',
        message: 'To everyone',
        category: NotificationCategory::COMMUNICATION,
    );

    Notification::assertSentTo($users, GenericNotification::class);
});

test('send from template', function () {
    Notification::fake();

    $user = User::factory()->create();
    $template = NotificationTemplate::factory()->create([
        'subject' => 'Hello {{ name }}',
        'body' => 'Welcome {{ name }}!',
        'category' => NotificationCategory::COMMUNICATION,
        'channels' => ['database'],
    ]);

    $this->service->sendFromTemplate(
        template: $template,
        recipients: $user,
        variables: ['name' => 'John'],
    );

    Notification::assertSentTo($user, GenericNotification::class);
});

test('send from template to multiple users', function () {
    Notification::fake();

    $users = User::factory()->count(3)->create();
    $template = NotificationTemplate::factory()->create([
        'subject' => 'Announcement',
        'body' => 'Important update!',
        'category' => NotificationCategory::SYSTEM,
    ]);

    $this->service->sendFromTemplate(
        template: $template,
        recipients: $users,
    );

    Notification::assertSentTo($users, GenericNotification::class);
});

test('send from template by slug', function () {
    Notification::fake();

    $user = User::factory()->create();
    NotificationTemplate::factory()->create([
        'slug' => 'welcome-email',
        'subject' => 'Welcome!',
        'body' => 'Hello there!',
        'category' => NotificationCategory::COMMUNICATION,
        'is_active' => true,
    ]);

    $this->service->sendFromTemplateBySlug(
        slug: 'welcome-email',
        recipients: $user,
    );

    Notification::assertSentTo($user, GenericNotification::class);
});

test('send from template by slug fails for inactive', function () {
    $user = User::factory()->create();
    NotificationTemplate::factory()->create([
        'slug' => 'inactive-template',
        'is_active' => false,
    ]);

    expect(fn () => $this->service->sendFromTemplateBySlug(
        slug: 'inactive-template',
        recipients: $user,
    ))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('send with custom channels', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->service->sendToUser(
        user: $user,
        title: 'Test',
        message: 'Test',
        category: NotificationCategory::SECURITY,
        channels: [NotificationChannel::DATABASE, NotificationChannel::EMAIL],
    );

    Notification::assertSentTo($user, GenericNotification::class, function ($notification) use ($user) {
        $channels = $notification->via($user);

        return in_array('database', $channels) && in_array('mail', $channels);
    });
});

test('send with action url and label', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->service->sendToUser(
        user: $user,
        title: 'Test',
        message: 'Test',
        category: NotificationCategory::TRANSACTIONAL,
        actionUrl: 'https://example.com/order/123',
        actionLabel: 'View Order',
    );

    Notification::assertSentTo($user, GenericNotification::class, function ($notification) use ($user) {
        $data = $notification->toArray($user);

        return $data['action_url'] === 'https://example.com/order/123'
            && $data['action_label'] === 'View Order';
    });
});
