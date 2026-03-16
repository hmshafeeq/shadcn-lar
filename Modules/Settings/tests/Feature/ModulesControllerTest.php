<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
});

test('super admin can view modules page', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $response = $this->actingAs($user)->get(route('dashboard.settings.modules'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('settings/modules/index')
        ->has('modules')
    );
});

test('non super admin cannot view modules page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard.settings.modules'));

    $response->assertForbidden();
});

test('super admin can toggle module', function () {
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $this->actingAs($user)->get(route('dashboard.settings.modules'));

    $response = $this->actingAs($user)
        ->from(route('dashboard.settings.modules'))
        ->patch(route('dashboard.settings.modules.toggle'), [
            '_token' => csrf_token(),
            'name' => 'Blog',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('cannot disable permission module', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $this->actingAs($user)->get(route('dashboard.settings.modules'));

    $response = $this->actingAs($user)
        ->from(route('dashboard.settings.modules'))
        ->patch(route('dashboard.settings.modules.toggle'), [
            '_token' => csrf_token(),
            'name' => 'Permission',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('toggle non existent module returns error', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $this->actingAs($user)->get(route('dashboard.settings.modules'));

    $response = $this->actingAs($user)
        ->from(route('dashboard.settings.modules'))
        ->patch(route('dashboard.settings.modules.toggle'), [
            '_token' => csrf_token(),
            'name' => 'NonExistentModule',
        ]);

    $response->assertNotFound();
});

test('toggle requires module name', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $this->actingAs($user)->get(route('dashboard.settings.modules'));

    $response = $this->actingAs($user)
        ->from(route('dashboard.settings.modules'))
        ->patch(route('dashboard.settings.modules.toggle'), [
            '_token' => csrf_token(),
        ]);

    $response->assertSessionHasErrors('name');
});

test('non super admin cannot toggle module', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard.settings.modules'));

    $response = $this->actingAs($user)
        ->from(route('dashboard.settings.modules'))
        ->patch(route('dashboard.settings.modules.toggle'), [
            '_token' => csrf_token(),
            'name' => 'Blog',
        ]);

    $response->assertForbidden();
});
