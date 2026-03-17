<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Currency, SavingsGoal};

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

    $this->account = Account::create([
        'user_id' => $this->user->id,
        'name' => 'Savings Account',
        'account_type' => 'bank',
        'currency_code' => 'USD',
        'initial_balance' => 5000,
        'current_balance' => 5000,
        'is_active' => true,
    ]);
});

function createSavingsGoal(array $attributes = []): SavingsGoal
{
    return SavingsGoal::create(array_merge([
        'user_id' => test()->user->id,
        'name' => 'Emergency Fund',
        'target_amount' => 10000,
        'current_amount' => 0,
        'currency_code' => 'USD',
        'target_date' => now()->addYear(),
        'status' => 'active',
    ], $attributes));
}

test('user can list savings goals', function () {
    Sanctum::actingAs($this->user);

    createSavingsGoal(['name' => 'Goal 1']);
    createSavingsGoal(['name' => 'Goal 2']);

    $this->getJson('/api/v1/finance/savings-goals')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('user can create savings goal', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/finance/savings-goals', [
        'name' => 'Vacation Fund',
        'target_amount' => 5000,
        'currency_code' => 'USD',
        'target_date' => now()->addMonths(6)->format('Y-m-d'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Vacation Fund');
});

test('user can view savings goal', function () {
    Sanctum::actingAs($this->user);

    $goal = createSavingsGoal();

    $this->getJson("/api/v1/finance/savings-goals/{$goal->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $goal->id);
});

test('user can update savings goal', function () {
    Sanctum::actingAs($this->user);

    $goal = createSavingsGoal();

    $this->putJson("/api/v1/finance/savings-goals/{$goal->id}", [
        'name' => 'Updated Goal',
        'target_amount' => 15000,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Goal')
        ->assertJsonPath('data.target_amount', 15000);
});

test('user can delete savings goal', function () {
    Sanctum::actingAs($this->user);

    $goal = createSavingsGoal();

    $this->deleteJson("/api/v1/finance/savings-goals/{$goal->id}")
        ->assertOk();

    $this->assertSoftDeleted('finance_savings_goals', ['id' => $goal->id]);
});

test('user can contribute to goal', function () {
    Sanctum::actingAs($this->user);

    $goal = createSavingsGoal();

    $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/contribute", [
        'amount' => 500,
    ])->assertCreated();

    $goal->refresh();
    expect($goal->current_amount)->toEqual(500);
});

test('user can withdraw from goal', function () {
    Sanctum::actingAs($this->user);

    $goal = createSavingsGoal();

    $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/contribute", [
        'amount' => 1000,
    ]);

    $goal->refresh();
    $previousAmount = $goal->current_amount;

    $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/withdraw", [
        'amount' => 300,
    ])->assertCreated();

    $goal->refresh();
    expect($goal->current_amount)->toBe($previousAmount - 300);
});

test('user can pause goal', function () {
    Sanctum::actingAs($this->user);

    $goal = createSavingsGoal(['status' => 'active']);

    $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/pause")
        ->assertOk();

    $goal->refresh();
    expect($goal->status)->toBe('paused');
});

test('user can resume goal', function () {
    Sanctum::actingAs($this->user);

    $goal = createSavingsGoal(['status' => 'paused']);

    $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/resume")
        ->assertOk();

    $goal->refresh();
    expect($goal->status)->toBe('active');
});

test('user can get savings summary', function () {
    Sanctum::actingAs($this->user);

    createSavingsGoal(['target_amount' => 10000, 'current_amount' => 3000]);
    createSavingsGoal(['target_amount' => 5000, 'current_amount' => 2000]);

    $this->getJson('/api/v1/finance/savings-goals-summary')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total_target',
                'total_saved',
                'total_remaining',
                'overall_progress',
                'active_goals_count',
                'completed_goals_count',
                'total_goals_count',
            ],
        ]);
});

test('user cannot access other users goal', function () {
    Sanctum::actingAs($this->user);

    $otherUser = User::factory()->create();
    $goal = SavingsGoal::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Goal',
        'target_amount' => 5000,
        'current_amount' => 0,
        'currency_code' => 'USD',
        'target_date' => now()->addYear(),
        'status' => 'active',
    ]);

    $this->getJson("/api/v1/finance/savings-goals/{$goal->id}")
        ->assertForbidden();
});

test('unauthenticated user cannot access goals', function () {
    $this->getJson('/api/v1/finance/savings-goals')
        ->assertUnauthorized();
});

test('auto sync updates goal when account balance changes', function () {
    $goal = createSavingsGoal([
        'target_account_id' => $this->account->id,
        'target_amount' => 10000,
        'current_amount' => 5000,
    ]);

    $this->account->updateBalance(1000);

    $goal->refresh();
    expect($goal->current_amount)->toEqual(6000);
});

test('auto sync completes goal when target reached', function () {
    $goal = createSavingsGoal([
        'target_account_id' => $this->account->id,
        'target_amount' => 5000,
        'current_amount' => 4000,
    ]);

    $this->account->updateBalance(1000);

    $goal->refresh();
    expect($goal->current_amount)->toEqual(6000);
    expect($goal->status)->toBe('completed');
    expect($goal->completed_at)->not->toBeNull();
});

test('auto sync reactivates goal when balance drops below target', function () {
    $goal = createSavingsGoal([
        'target_account_id' => $this->account->id,
        'target_amount' => 5000,
        'current_amount' => 5000,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $this->account->updateBalance(-1000);

    $goal->refresh();
    expect($goal->current_amount)->toEqual(4000);
    expect($goal->status)->toBe('active');
    expect($goal->completed_at)->toBeNull();
});

test('auto sync skips currency mismatch', function () {
    Currency::create([
        'code' => 'VND',
        'name' => 'Vietnamese Dong',
        'symbol' => 'd',
        'decimal_places' => 0,
    ]);

    $goal = createSavingsGoal([
        'target_account_id' => $this->account->id,
        'currency_code' => 'VND',
        'current_amount' => 1000,
    ]);

    $this->account->updateBalance(2000);

    $goal->refresh();
    expect($goal->current_amount)->toEqual(1000);
});

test('auto sync skips paused goals', function () {
    $goal = createSavingsGoal([
        'target_account_id' => $this->account->id,
        'current_amount' => 3000,
        'status' => 'paused',
    ]);

    $this->account->updateBalance(1000);

    $goal->refresh();
    expect($goal->current_amount)->toEqual(3000);
});

test('initial sync on goal creation with linked account', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/finance/savings-goals', [
        'name' => 'Linked Goal',
        'target_amount' => 20000,
        'currency_code' => 'USD',
        'target_account_id' => $this->account->id,
    ])->assertCreated();

    $goal = SavingsGoal::where('name', 'Linked Goal')->first();
    expect($goal->current_amount)->toEqual(5000);
});

test('sync on goal update when account linked', function () {
    Sanctum::actingAs($this->user);

    $goal = createSavingsGoal(['current_amount' => 0]);

    $this->putJson("/api/v1/finance/savings-goals/{$goal->id}", [
        'target_account_id' => $this->account->id,
    ])->assertOk();

    $goal->refresh();
    expect($goal->current_amount)->toEqual(5000);
});
