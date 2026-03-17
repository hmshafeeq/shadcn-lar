<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationTemplate;
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

test('admin can view templates index', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('dashboard.notifications.templates.index'));

    $response->assertStatus(200);
});

test('admin can view create template page', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('dashboard.notifications.templates.create'));

    $response->assertStatus(200);
});

test('admin can create template', function () {
    $data = [
        'name' => 'Test Template',
        'subject' => 'Test Subject',
        'body' => 'Test body content',
        'category' => NotificationCategory::SYSTEM->value,
        'channels' => [NotificationChannel::DATABASE->value],
        'variables' => ['user_name'],
        'is_active' => true,
    ];

    $response = $this->actingAs($this->admin)
        ->post(route('dashboard.notifications.templates.store'), $data);

    $response->assertRedirect(route('dashboard.notifications.templates.index'));

    $this->assertDatabaseHas('notification_templates', [
        'name' => 'Test Template',
        'category' => 'system',
    ]);
});

test('admin can view edit template page', function () {
    $template = NotificationTemplate::factory()->create();

    $response = $this->actingAs($this->admin)
        ->get(route('dashboard.notifications.templates.edit', $template));

    $response->assertStatus(200);
});

test('admin can update template', function () {
    $template = NotificationTemplate::factory()->create([
        'name' => 'Old Name',
    ]);

    $data = [
        'name' => 'Updated Name',
        'subject' => 'Updated Subject',
        'body' => 'Updated body',
        'category' => NotificationCategory::MARKETING->value,
        'channels' => [NotificationChannel::EMAIL->value],
        'is_active' => false,
    ];

    $response = $this->actingAs($this->admin)
        ->put(route('dashboard.notifications.templates.update', $template), $data);

    $response->assertRedirect(route('dashboard.notifications.templates.index'));

    $this->assertDatabaseHas('notification_templates', [
        'id' => $template->id,
        'name' => 'Updated Name',
    ]);
});

test('admin can delete template', function () {
    $template = NotificationTemplate::factory()->create();

    $response = $this->actingAs($this->admin)
        ->delete(route('dashboard.notifications.templates.destroy', $template));

    $response->assertRedirect(route('dashboard.notifications.templates.index'));

    $this->assertSoftDeleted('notification_templates', ['id' => $template->id]);
});

test('admin can toggle template status', function () {
    $template = NotificationTemplate::factory()->create(['is_active' => true]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('dashboard.notifications.templates.toggle-status', $template));

    $response->assertStatus(200);
    $response->assertJson(['is_active' => false]);

    $template->refresh();
    expect($template->is_active)->toBeFalse();
});

test('regular user cannot access templates', function () {
    $response = $this->actingAs($this->user)
        ->get(route('dashboard.notifications.templates.index'));

    $response->assertStatus(403);
});

test('create template validates required fields', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('dashboard.notifications.templates.store'), []);

    $response->assertSessionHasErrors(['name', 'subject', 'body', 'category', 'channels']);
});
