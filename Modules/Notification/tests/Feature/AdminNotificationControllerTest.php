<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Notifications\GenericNotification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = [
        'notifications.templates.view',
        'notifications.templates.create',
        'notifications.templates.edit',
        'notifications.templates.delete',
        'notifications.send',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $adminRole = Role::create(['name' => 'super-admin']);
    $adminRole->givePermissionTo(Permission::all());

    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);

    $this->user = User::factory()->create();
});

test('admin can view send notification page', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('dashboard.notifications.send'));

    $response->assertStatus(200);
});

test('admin can send notification to users', function () {
    Notification::fake();

    $recipients = User::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('dashboard.notifications.send.store'), [
            'recipient_type' => 'users',
            'user_ids' => $recipients->pluck('id')->toArray(),
            'use_template' => false,
            'title' => 'Test Notification',
            'message' => 'This is a test message',
            'category' => NotificationCategory::SYSTEM->value,
            'channels' => [NotificationChannel::DATABASE->value],
        ]);

    $response->assertStatus(200);
    $response->assertJson(['message' => 'Notification sent to 3 user(s).']);

    Notification::assertSentTo($recipients, GenericNotification::class);
});

test('admin can send notification to role', function () {
    Notification::fake();

    $testRole = Role::create(['name' => 'tester']);
    $testers = User::factory()->count(2)->create();
    foreach ($testers as $tester) {
        $tester->assignRole($testRole);
    }

    $response = $this->actingAs($this->admin)
        ->postJson(route('dashboard.notifications.send.store'), [
            'recipient_type' => 'roles',
            'role_ids' => [$testRole->id],
            'use_template' => false,
            'title' => 'Tester Notification',
            'message' => 'For testers only',
            'category' => NotificationCategory::SYSTEM->value,
            'channels' => [NotificationChannel::DATABASE->value],
        ]);

    $response->assertStatus(200);

    Notification::assertSentTo($testers, GenericNotification::class);
});

test('admin can search users', function () {
    User::factory()->create(['name' => 'Xylocarp Doe', 'email' => 'xylocarp@example.com']);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

    $response = $this->actingAs($this->admin)
        ->getJson(route('dashboard.notifications.search-users', ['q' => 'Xylocarp']));

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'users');
    $response->assertJsonPath('users.0.label', 'Xylocarp Doe');
});

test('regular user cannot send notifications', function () {
    $response = $this->actingAs($this->user)
        ->get(route('dashboard.notifications.send'));

    $response->assertStatus(403);
});

test('send notification validates recipient type', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('dashboard.notifications.send.store'), [
            'recipient_type' => 'invalid',
            'use_template' => false,
            'title' => 'Test',
            'message' => 'Test',
            'category' => NotificationCategory::SYSTEM->value,
            'channels' => [NotificationChannel::DATABASE->value],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['recipient_type']);
});

test('admin can send notification from template', function () {
    Notification::fake();

    $template = NotificationTemplate::factory()->create([
        'subject' => 'Hello {{ user_name }}',
        'body' => 'Welcome {{ user_name }}!',
        'category' => NotificationCategory::COMMUNICATION,
        'channels' => ['database'],
        'is_active' => true,
    ]);

    $recipient = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('dashboard.notifications.send.store'), [
            'recipient_type' => 'users',
            'user_ids' => [$recipient->id],
            'use_template' => true,
            'template_id' => $template->id,
            'template_variables' => ['user_name' => 'John'],
        ]);

    $response->assertStatus(200);

    Notification::assertSentTo($recipient, GenericNotification::class);
});
