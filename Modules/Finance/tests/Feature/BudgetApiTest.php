<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Budget, Category, Currency};

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

    $this->category = Category::create([
        'name' => 'Food & Dining',
        'type' => 'expense',
        'color' => '#ff0000',
    ]);
});

function createBudget(array $attributes = []): Budget
{
    return Budget::create(array_merge([
        'user_id' => test()->user->id,
        'name' => 'Food Budget',
        'category_id' => test()->category->id,
        'allocated_amount' => 500,
        'spent_amount' => 0,
        'currency_code' => 'USD',
        'period_type' => 'monthly',
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'is_active' => true,
    ], $attributes));
}

test('user can list budgets', function () {
    Sanctum::actingAs($this->user);

    createBudget(['name' => 'Budget 1']);
    createBudget(['name' => 'Budget 2']);

    $this->getJson('/api/v1/finance/budgets')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('user can create budget', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/finance/budgets', [
        'name' => 'Entertainment Budget',
        'category_id' => $this->category->id,
        'allocated_amount' => 300,
        'currency_code' => 'USD',
        'period_type' => 'monthly',
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
        'is_active' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Entertainment Budget');
});

test('user can view budget', function () {
    Sanctum::actingAs($this->user);

    $budget = createBudget();

    $this->getJson("/api/v1/finance/budgets/{$budget->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $budget->id);
});

test('user can update budget', function () {
    Sanctum::actingAs($this->user);

    $budget = createBudget();

    $this->putJson("/api/v1/finance/budgets/{$budget->id}", [
        'name' => 'Updated Budget',
        'allocated_amount' => 600,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Budget')
        ->assertJsonPath('data.allocated_amount', 600);
});

test('user can delete budget', function () {
    Sanctum::actingAs($this->user);

    $budget = createBudget();

    $this->deleteJson("/api/v1/finance/budgets/{$budget->id}")
        ->assertOk();

    $this->assertDatabaseMissing('finance_budgets', ['id' => $budget->id]);
});

test('user can refresh budget', function () {
    Sanctum::actingAs($this->user);

    $budget = createBudget();

    $this->postJson("/api/v1/finance/budgets/{$budget->id}/refresh")
        ->assertOk();
});

test('user can get budget summary', function () {
    Sanctum::actingAs($this->user);

    createBudget(['allocated_amount' => 500, 'spent_amount' => 200]);
    createBudget(['allocated_amount' => 300, 'spent_amount' => 100]);

    $this->getJson('/api/v1/finance/budgets-summary')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total_allocated',
                'total_spent',
                'total_remaining',
                'active_budgets_count',
                'over_budget_count',
            ],
        ]);
});

test('user cannot access other users budget', function () {
    Sanctum::actingAs($this->user);

    $otherUser = User::factory()->create();
    $budget = Budget::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Budget',
        'category_id' => $this->category->id,
        'allocated_amount' => 500,
        'spent_amount' => 0,
        'currency_code' => 'USD',
        'period_type' => 'monthly',
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/finance/budgets/{$budget->id}")
        ->assertForbidden();
});

test('unauthenticated user cannot access budgets', function () {
    $this->getJson('/api/v1/finance/budgets')
        ->assertUnauthorized();
});
