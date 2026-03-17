<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Currency, FinancialPlan};

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

function createPlan(array $attributes = []): FinancialPlan
{
    return FinancialPlan::create(array_merge([
        'user_id' => test()->user->id,
        'name' => 'Annual Budget 2026',
        'description' => 'Financial plan for 2026',
        'start_year' => 2026,
        'end_year' => 2026,
        'currency_code' => 'USD',
        'status' => 'draft',
    ], $attributes));
}

test('user can list plans', function () {
    Sanctum::actingAs($this->user);

    createPlan(['name' => 'Plan 1']);
    createPlan(['name' => 'Plan 2']);

    $this->getJson('/api/v1/finance/plans')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('user can create plan', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/finance/plans', [
        'name' => 'New Financial Plan',
        'description' => 'My financial plan',
        'start_year' => 2026,
        'end_year' => 2026,
        'currency_code' => 'USD',
        'status' => 'draft',
        'periods' => [
            [
                'year' => 2026,
                'items' => [
                    [
                        'name' => 'Salary',
                        'type' => 'income',
                        'planned_amount' => 60000,
                        'recurrence' => 'monthly',
                    ],
                    [
                        'name' => 'Rent',
                        'type' => 'expense',
                        'planned_amount' => 18000,
                        'recurrence' => 'monthly',
                    ],
                ],
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'New Financial Plan');
});

test('user can view plan', function () {
    Sanctum::actingAs($this->user);

    $plan = createPlan();

    $this->getJson("/api/v1/finance/plans/{$plan->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $plan->id);
});

test('user can update plan', function () {
    Sanctum::actingAs($this->user);

    $plan = createPlan();
    $plan->periods()->create([
        'year' => 2026,
        'planned_income' => 0,
        'planned_expense' => 0,
    ]);

    $this->putJson("/api/v1/finance/plans/{$plan->id}", [
        'name' => 'Updated Plan Name',
        'status' => 'active',
        'periods' => [
            [
                'id' => $plan->periods->first()->id,
                'year' => 2026,
                'items' => [],
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Plan Name')
        ->assertJsonPath('data.status', 'active');
});

test('user can delete plan', function () {
    Sanctum::actingAs($this->user);

    $plan = createPlan();

    $this->deleteJson("/api/v1/finance/plans/{$plan->id}")
        ->assertOk();

    $this->assertDatabaseMissing('finance_plans', ['id' => $plan->id]);
});

test('user can compare plan', function () {
    Sanctum::actingAs($this->user);

    $plan = createPlan();
    $plan->periods()->create([
        'year' => 2026,
        'planned_income' => 60000,
        'planned_expense' => 40000,
    ]);

    $this->getJson("/api/v1/finance/plans/{$plan->id}/compare")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'plan',
                'comparison',
            ],
        ]);
});

test('user cannot access other users plan', function () {
    Sanctum::actingAs($this->user);

    $otherUser = User::factory()->create();
    $plan = FinancialPlan::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Plan',
        'start_year' => 2026,
        'end_year' => 2026,
        'currency_code' => 'USD',
        'status' => 'draft',
    ]);

    $this->getJson("/api/v1/finance/plans/{$plan->id}")
        ->assertForbidden();
});

test('unauthenticated user cannot access plans', function () {
    $this->getJson('/api/v1/finance/plans')
        ->assertUnauthorized();
});
