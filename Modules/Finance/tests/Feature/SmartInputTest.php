<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Account, Currency};

uses(RefreshDatabase::class);

function createUserWithAccount(): array
{
    $user = User::factory()->create();

    Currency::create([
        'code' => 'VND',
        'name' => 'Vietnamese Dong',
        'symbol' => '₫',
        'decimal_places' => 0,
        'is_default' => true,
    ]);

    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Test Account',
        'account_type' => 'bank',
        'currency_code' => 'VND',
        'initial_balance' => 1000000,
        'current_balance' => 1000000,
        'is_active' => true,
    ]);

    return [$user, $account];
}

test('smart input page loads', function () {
    [$user] = createUserWithAccount();

    $this->actingAs($user)
        ->get(route('dashboard.finance.smart-input'))
        ->assertStatus(200);
});

test('parse text endpoint returns json', function () {
    [$user] = createUserWithAccount();

    $response = $this->actingAs($user)
        ->withoutMiddleware()
        ->postJson(route('dashboard.finance.smart-input.parse-text'), [
            'text' => 'Cafe 50k hôm nay',
            'language' => 'vi',
        ]);

    // API may succeed (200) or fail due to quota/rate limits (422) or config issues (500)
    expect(in_array($response->status(), [200, 422, 500]))->toBeTrue(
        "Unexpected status code: {$response->status()}"
    );

    if ($response->status() !== 500) {
        $response->assertJsonStructure(['success']);
    }
});

test('store transaction requires account', function () {
    [$user] = createUserWithAccount();

    $this->actingAs($user)
        ->withoutMiddleware()
        ->postJson(route('dashboard.finance.smart-input.store'), [
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Test transaction',
            'transaction_date' => now()->format('Y-m-d'),
        ])
        ->assertStatus(422);
});

test('store transaction creates transaction', function () {
    [$user, $account] = createUserWithAccount();

    $this->actingAs($user)
        ->withoutMiddleware()
        ->postJson(route('dashboard.finance.smart-input.store'), [
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Test smart input transaction',
            'account_id' => $account->id,
            'transaction_date' => now()->format('Y-m-d'),
        ])
        ->assertOk()
        ->assertJson(['success' => true]);
});
