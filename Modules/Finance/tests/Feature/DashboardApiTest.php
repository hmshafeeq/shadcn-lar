<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Budget, Category, Currency, Transaction};

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

test('user can get dashboard data', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/finance/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => [
                    'total_assets',
                    'total_liabilities',
                    'net_worth',
                    'total_balance',
                    'currency_code',
                    'accounts_count',
                ],
                'recent_transactions',
                'budgets',
                'spending_trend',
                'recurring_projection',
                'upcoming_recurrings',
            ],
        ]);
});

test('dashboard shows recent transactions', function () {
    Sanctum::actingAs($this->user);

    Transaction::create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
        'transaction_type' => 'expense',
        'amount' => 100,
        'currency_code' => 'USD',
        'transaction_date' => now(),
        'description' => 'Test Transaction',
    ]);

    $response = $this->getJson('/api/v1/finance/dashboard');

    $response->assertOk();
    expect($response->json('data.recent_transactions'))->toHaveCount(1);
});

test('dashboard shows active budgets', function () {
    Sanctum::actingAs($this->user);

    $category = Category::create([
        'name' => 'Food',
        'type' => 'expense',
        'color' => '#ff0000',
    ]);

    Budget::create([
        'user_id' => $this->user->id,
        'name' => 'Food Budget',
        'category_id' => $category->id,
        'allocated_amount' => 500,
        'spent_amount' => 0,
        'currency_code' => 'USD',
        'period_type' => 'monthly',
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/finance/dashboard');

    $response->assertOk();
    expect($response->json('data.budgets'))->toHaveCount(1);
});

test('dashboard calculates net worth correctly', function () {
    Sanctum::actingAs($this->user);

    Account::create([
        'user_id' => $this->user->id,
        'name' => 'Credit Card',
        'account_type' => 'credit_card',
        'currency_code' => 'USD',
        'initial_balance' => 5000,
        'current_balance' => 4000,
        'is_active' => true,
        'has_credit_limit' => true,
    ]);

    $response = $this->getJson('/api/v1/finance/dashboard');

    $response->assertOk();
    $summary = $response->json('data.summary');

    expect($summary['total_assets'])->toBe(10000);
    expect($summary['total_liabilities'])->toBe(1000);
    expect($summary['net_worth'])->toBe(9000);
});

test('unauthenticated user cannot access dashboard', function () {
    $this->getJson('/api/v1/finance/dashboard')
        ->assertUnauthorized();
});
