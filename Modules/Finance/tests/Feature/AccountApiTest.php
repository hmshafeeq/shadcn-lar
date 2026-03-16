<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Currency};

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

function createAccount(array $attributes = []): Account
{
    return Account::create(array_merge([
        'user_id' => test()->user->id,
        'name' => 'Test Account',
        'account_type' => 'bank',
        'currency_code' => 'USD',
        'initial_balance' => 1000,
        'current_balance' => 1000,
        'is_active' => true,
    ], $attributes));
}

test('user can list accounts', function () {
    Sanctum::actingAs($this->user);

    createAccount(['name' => 'Account 1']);
    createAccount(['name' => 'Account 2']);
    createAccount(['name' => 'Account 3']);

    $this->getJson('/api/v1/finance/accounts')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('user can create account', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/finance/accounts', [
        'name' => 'My Bank Account',
        'account_type' => 'bank',
        'currency_code' => 'USD',
        'initial_balance' => 1000.50,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'My Bank Account')
        ->assertJsonPath('data.initial_balance', 1000.50);
});

test('user can view own account', function () {
    Sanctum::actingAs($this->user);

    $account = createAccount();

    $this->getJson("/api/v1/finance/accounts/{$account->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $account->id);
});

test('user cannot view other users account', function () {
    Sanctum::actingAs($this->user);

    $otherUser = User::factory()->create();
    $account = createAccount(['user_id' => $otherUser->id]);

    $this->getJson("/api/v1/finance/accounts/{$account->id}")
        ->assertForbidden();
});

test('user can update account', function () {
    Sanctum::actingAs($this->user);

    $account = createAccount();

    $this->putJson("/api/v1/finance/accounts/{$account->id}", [
        'name' => 'Updated Name',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
});

test('user can delete account without transactions', function () {
    Sanctum::actingAs($this->user);

    $account = createAccount();

    $this->deleteJson("/api/v1/finance/accounts/{$account->id}")
        ->assertOk();

    $this->assertSoftDeleted('finance_accounts', ['id' => $account->id]);
});

test('unauthenticated user cannot access accounts', function () {
    $this->getJson('/api/v1/finance/accounts')
        ->assertUnauthorized();
});
