<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Category, Currency};

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    Currency::create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_default' => true,
    ]);
});

function createCategory(array $attributes = []): Category
{
    return Category::create(array_merge([
        'user_id' => test()->user->id,
        'name' => 'Test Category',
        'type' => 'expense',
        'color' => '#ff0000',
        'icon' => 'shopping-cart',
        'is_active' => true,
    ], $attributes));
}

test('user can list categories', function () {
    Sanctum::actingAs($this->user);

    createCategory(['name' => 'Food']);
    createCategory(['name' => 'Transport']);

    // Create a system category (user_id = null)
    Category::create([
        'name' => 'System Category',
        'type' => 'expense',
        'color' => '#00ff00',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/finance/categories');

    $response->assertOk();
    // Should include user categories and system categories
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(3);
});

test('user can filter by type', function () {
    Sanctum::actingAs($this->user);

    createCategory(['name' => 'Expense Cat', 'type' => 'expense']);
    createCategory(['name' => 'Income Cat', 'type' => 'income']);

    $response = $this->getJson('/api/v1/finance/categories?type=income');

    $response->assertOk();
    foreach ($response->json('data') as $category) {
        expect($category['type'])->toBe('income');
    }
});

test('user can create category', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/finance/categories', [
        'name' => 'New Category',
        'type' => 'expense',
        'color' => '#0000ff',
        'icon' => 'cart',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'New Category');
});

test('user can view category', function () {
    Sanctum::actingAs($this->user);

    $category = createCategory();

    $this->getJson("/api/v1/finance/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $category->id);
});

test('user can update own category', function () {
    Sanctum::actingAs($this->user);

    $category = createCategory();

    $this->putJson("/api/v1/finance/categories/{$category->id}", [
        'name' => 'Updated Category',
        'color' => '#00ff00',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Category')
        ->assertJsonPath('data.color', '#00ff00');
});

test('user can delete own category', function () {
    Sanctum::actingAs($this->user);

    $category = createCategory();

    $this->deleteJson("/api/v1/finance/categories/{$category->id}")
        ->assertOk();

    $this->assertDatabaseMissing('finance_categories', ['id' => $category->id]);
});

test('user cannot delete system category', function () {
    Sanctum::actingAs($this->user);

    $systemCategory = Category::create([
        'name' => 'System Category',
        'type' => 'expense',
        'color' => '#00ff00',
        'is_active' => true,
    ]);

    $this->deleteJson("/api/v1/finance/categories/{$systemCategory->id}")
        ->assertForbidden();
});

test('user cannot modify other users category', function () {
    Sanctum::actingAs($this->user);

    $otherUser = User::factory()->create();
    $category = Category::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Category',
        'type' => 'expense',
        'color' => '#ff0000',
        'is_active' => true,
    ]);

    $this->putJson("/api/v1/finance/categories/{$category->id}", [
        'name' => 'Hacked',
    ])->assertForbidden();
});

test('unauthenticated user cannot access categories', function () {
    $this->getJson('/api/v1/finance/categories')
        ->assertUnauthorized();
});
