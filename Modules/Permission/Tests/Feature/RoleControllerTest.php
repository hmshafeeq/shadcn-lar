<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\{Permission, Role};

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['roles.view', 'roles.create', 'roles.edit', 'roles.delete'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'roles.view',
        'roles.create',
        'roles.edit',
        'roles.delete',
    ]);
});

test('it can list all roles', function () {
    Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    Role::create(['name' => 'Editor', 'guard_name' => 'web']);

    $response = $this->actingAs($this->user)
        ->get('/dashboard/roles');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('roles/index')
            ->has('roles.data', 2)
        );
});

test('it can create a role', function () {
    $roleData = [
        'name' => 'Manager',
        'permissions' => [],
    ];

    $response = $this->actingAs($this->user)
        ->post('/dashboard/roles', $roleData);

    $response->assertRedirect();

    $this->assertDatabaseHas('roles', [
        'name' => 'Manager',
    ]);
});

test('it requires authentication for all endpoints', function () {
    $role = Role::create(['name' => 'Test', 'guard_name' => 'web']);

    $this->get('/dashboard/roles')->assertRedirect('/login');
    $this->post('/dashboard/roles', [])->assertRedirect('/login');
    $this->get("/dashboard/roles/{$role->id}")->assertRedirect('/login');
    $this->put("/dashboard/roles/{$role->id}", [])->assertRedirect('/login');
    $this->delete("/dashboard/roles/{$role->id}")->assertRedirect('/login');
});
