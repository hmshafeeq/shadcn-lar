<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Currency, RecurringTransaction};

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
        'name' => 'Test Account',
        'account_type' => 'bank',
        'currency_code' => 'USD',
        'initial_balance' => 10000,
        'current_balance' => 10000,
        'is_active' => true,
    ]);
});

function createRecurring(array $attributes = []): RecurringTransaction
{
    return RecurringTransaction::create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'name' => 'Monthly Salary',
        'transaction_type' => 'income',
        'amount' => 5000,
        'currency_code' => 'USD',
        'frequency' => 'monthly',
        'day_of_month' => 1,
        'start_date' => now()->startOfMonth(),
        'next_run_date' => now()->addMonth()->startOfMonth(),
        'is_active' => true,
        'auto_create' => true,
    ], $attributes));
}

test('user can list recurring transactions', function () {
    Sanctum::actingAs($this->user);

    createRecurring(['name' => 'Salary']);
    createRecurring(['name' => 'Rent', 'transaction_type' => 'expense']);

    $this->getJson('/api/v1/finance/recurring-transactions')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('user can filter active only', function () {
    Sanctum::actingAs($this->user);

    createRecurring(['name' => 'Active', 'is_active' => true]);
    createRecurring(['name' => 'Inactive', 'is_active' => false]);

    $this->getJson('/api/v1/finance/recurring-transactions?active_only=1')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('user can create recurring transaction', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/finance/recurring-transactions', [
        'name' => 'Monthly Rent',
        'account_id' => $this->account->id,
        'transaction_type' => 'expense',
        'amount' => 1500,
        'frequency' => 'monthly',
        'day_of_month' => 1,
        'start_date' => now()->format('Y-m-d'),
        'is_active' => true,
        'auto_create' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Monthly Rent')
        ->assertJsonPath('data.amount', 1500);
});

test('user can view recurring transaction', function () {
    Sanctum::actingAs($this->user);

    $recurring = createRecurring();

    $this->getJson("/api/v1/finance/recurring-transactions/{$recurring->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $recurring->id);
});

test('user can update recurring transaction', function () {
    Sanctum::actingAs($this->user);

    $recurring = createRecurring();

    $this->putJson("/api/v1/finance/recurring-transactions/{$recurring->id}", [
        'name' => 'Updated Name',
        'amount' => 6000,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.amount', 6000);
});

test('user can delete recurring transaction', function () {
    Sanctum::actingAs($this->user);

    $recurring = createRecurring();

    $this->deleteJson("/api/v1/finance/recurring-transactions/{$recurring->id}")
        ->assertOk();

    $this->assertSoftDeleted('finance_recurring_transactions', ['id' => $recurring->id]);
});

test('user can toggle recurring transaction', function () {
    Sanctum::actingAs($this->user);

    $recurring = createRecurring(['is_active' => true]);

    $this->postJson("/api/v1/finance/recurring-transactions/{$recurring->id}/toggle")
        ->assertOk();

    $recurring->refresh();
    expect($recurring->is_active)->toBeFalse();
});

test('user can get preview', function () {
    Sanctum::actingAs($this->user);

    $recurring = createRecurring();

    $this->getJson("/api/v1/finance/recurring-transactions/{$recurring->id}/preview")
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('user can get upcoming', function () {
    Sanctum::actingAs($this->user);

    createRecurring(['next_run_date' => now()->addDays(5)]);

    $this->getJson('/api/v1/finance/recurring-transactions-upcoming?days=30')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('user can get projection', function () {
    Sanctum::actingAs($this->user);

    createRecurring();

    $this->getJson('/api/v1/finance/recurring-transactions-projection')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('user cannot access other users recurring', function () {
    Sanctum::actingAs($this->user);

    $otherUser = User::factory()->create();
    $otherAccount = Account::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Account',
        'account_type' => 'bank',
        'currency_code' => 'USD',
        'initial_balance' => 5000,
        'current_balance' => 5000,
        'is_active' => true,
    ]);

    $recurring = RecurringTransaction::create([
        'user_id' => $otherUser->id,
        'account_id' => $otherAccount->id,
        'name' => 'Other Recurring',
        'transaction_type' => 'expense',
        'amount' => 100,
        'currency_code' => 'USD',
        'frequency' => 'monthly',
        'start_date' => now(),
        'next_run_date' => now()->addMonth(),
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/finance/recurring-transactions/{$recurring->id}")
        ->assertForbidden();
});

test('unauthenticated user cannot access recurring', function () {
    $this->getJson('/api/v1/finance/recurring-transactions')
        ->assertUnauthorized();
});
