<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Category, Currency, Transaction};

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

function createReportCategory(string $type, string $name): Category
{
    return Category::create([
        'name' => $name,
        'type' => $type,
        'color' => '#3b82f6',
    ]);
}

function createReportTransaction(array $attributes = []): Transaction
{
    return Transaction::create(array_merge([
        'account_id' => test()->account->id,
        'user_id' => test()->user->id,
        'transaction_type' => 'expense',
        'amount' => 100,
        'currency_code' => 'USD',
        'transaction_date' => now(),
        'description' => 'Test Transaction',
    ], $attributes));
}

test('user can get overview report', function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('This test requires MySQL (DATE_FORMAT function)');
    }

    Sanctum::actingAs($this->user);

    $category = createReportCategory('expense', 'Food');
    createReportTransaction(['amount' => 500, 'transaction_type' => 'expense', 'category_id' => $category->id]);
    createReportTransaction(['amount' => 1000, 'transaction_type' => 'income']);

    $this->getJson('/api/v1/finance/reports/overview?range=30d')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => ['total_income', 'total_expense', 'net_change', 'savings_rate', 'transaction_count'],
                'trend',
                'currency_code',
                'date_from',
                'date_to',
            ],
        ])
        ->assertJsonPath('data.summary.total_income', 1000)
        ->assertJsonPath('data.summary.total_expense', 500)
        ->assertJsonPath('data.summary.transaction_count', 2);
})->group('mysql');

test('user can get income expense trend', function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('This test requires MySQL (DATE_FORMAT function)');
    }

    Sanctum::actingAs($this->user);

    createReportTransaction(['amount' => 500, 'transaction_type' => 'expense']);
    createReportTransaction(['amount' => 1000, 'transaction_type' => 'income']);

    $this->getJson('/api/v1/finance/reports/income-expense-trend?range=30d')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['period', 'income', 'expense', 'net']],
            'currency_code',
        ]);
})->group('mysql');

test('user can get category breakdown', function () {
    Sanctum::actingAs($this->user);

    $food = createReportCategory('expense', 'Food');
    $transport = createReportCategory('expense', 'Transport');

    createReportTransaction(['amount' => 300, 'category_id' => $food->id]);
    createReportTransaction(['amount' => 200, 'category_id' => $transport->id]);

    $this->getJson('/api/v1/finance/reports/category-breakdown?type=expense&range=30d')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'color', 'amount', 'percentage']],
            'type',
            'currency_code',
        ])
        ->assertJsonCount(2, 'data');
});

test('user can get account distribution', function () {
    Sanctum::actingAs($this->user);

    Account::create([
        'user_id' => $this->user->id,
        'name' => 'Investment Account',
        'account_type' => 'investment',
        'currency_code' => 'USD',
        'initial_balance' => 5000,
        'current_balance' => 5500,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/finance/reports/account-distribution')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['type', 'label', 'balance', 'count', 'is_liability']],
            'currency_code',
        ]);
});

test('user can get cashflow analysis', function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('This test requires MySQL (DATE_FORMAT function)');
    }

    Sanctum::actingAs($this->user);

    createReportTransaction(['amount' => 500, 'transaction_type' => 'expense']);
    createReportTransaction(['amount' => 2000, 'transaction_type' => 'income']);

    $this->getJson('/api/v1/finance/reports/cashflow-analysis')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'monthly_data' => [['period', 'label', 'passive_income', 'active_income', 'total_income', 'expense', 'surplus', 'passive_coverage']],
                'averages' => ['passive_income', 'expense', 'coverage'],
                'financial_freedom_progress',
            ],
            'currency_code',
        ]);
})->group('mysql');

test('user can get net worth', function () {
    Sanctum::actingAs($this->user);

    Account::create([
        'user_id' => $this->user->id,
        'name' => 'Credit Card',
        'account_type' => 'credit_card',
        'currency_code' => 'USD',
        'initial_balance' => 0,
        'current_balance' => -1500,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/finance/reports/net-worth');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['total_assets', 'total_liabilities', 'net_worth', 'accounts_count'],
            'currency_code',
        ]);

    expect($response->json('data.total_assets'))->toBe(10000);
    expect($response->json('data.total_liabilities'))->toBe(1500);
    expect($response->json('data.net_worth'))->toBe(8500);
});

test('overview supports custom date range', function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('This test requires MySQL (DATE_FORMAT function)');
    }

    Sanctum::actingAs($this->user);

    createReportTransaction([
        'amount' => 100,
        'transaction_date' => now()->subDays(5),
    ]);

    $this->getJson('/api/v1/finance/reports/overview?range=custom&start='.now()->subDays(10)->format('Y-m-d').'&end='.now()->format('Y-m-d'))
        ->assertOk()
        ->assertJsonPath('data.summary.transaction_count', 1);
})->group('mysql');

test('unauthenticated user cannot access reports', function () {
    $this->getJson('/api/v1/finance/reports/overview')
        ->assertUnauthorized();
});
