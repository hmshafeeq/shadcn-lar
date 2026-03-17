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

function createTransaction(array $attributes = []): Transaction
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

test('user can update transaction amount', function () {
    Sanctum::actingAs($this->user);

    $transaction = createTransaction(['amount' => 100]);

    // Manually adjust balance for test (simulating what store would do)
    $this->account->update(['current_balance' => 9900]);

    $response = $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
        'amount' => 150,
    ]);

    $response->assertOk();
    expect($response->json('data.amount'))->toEqual(150);

    // Balance should be adjusted: 9900 - 50 (extra expense) = 9850
    $this->account->refresh();
    expect($this->account->current_balance)->toEqual(9850);
});

test('user can update transaction date', function () {
    Sanctum::actingAs($this->user);

    $transaction = createTransaction();
    $newDate = now()->subDays(5)->format('Y-m-d');

    $response = $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
        'transaction_date' => $newDate,
    ]);

    $response->assertOk();
    expect($response->json('data.transaction_date'))->toBe($newDate);
});

test('user can update transaction description', function () {
    Sanctum::actingAs($this->user);

    $transaction = createTransaction();

    $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
        'description' => 'Updated Description',
    ])
        ->assertOk()
        ->assertJsonPath('data.description', 'Updated Description');
});

test('user can update transaction category', function () {
    Sanctum::actingAs($this->user);

    $category = Category::create([
        'name' => 'Food',
        'type' => 'expense',
        'color' => '#ff0000',
    ]);

    $transaction = createTransaction();

    $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
        'category_id' => $category->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.category_id', $category->id);
});

test('user cannot update transfer transaction', function () {
    Sanctum::actingAs($this->user);

    $otherAccount = Account::create([
        'user_id' => $this->user->id,
        'name' => 'Other Account',
        'account_type' => 'bank',
        'currency_code' => 'USD',
        'initial_balance' => 5000,
        'current_balance' => 5000,
        'is_active' => true,
    ]);

    $debitTx = createTransaction([
        'transaction_type' => 'expense',
        'transfer_account_id' => $otherAccount->id,
    ]);

    $creditTx = createTransaction([
        'account_id' => $otherAccount->id,
        'transaction_type' => 'income',
        'transfer_account_id' => $this->account->id,
    ]);

    $debitTx->update(['transfer_transaction_id' => $creditTx->id]);
    $creditTx->update(['transfer_transaction_id' => $debitTx->id]);

    $this->putJson("/api/v1/finance/transactions/{$debitTx->id}", [
        'amount' => 200,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Transfer transactions cannot be edited. Please delete and create a new transfer.');
});

test('user cannot update other users transaction', function () {
    Sanctum::actingAs($this->user);

    $otherUser = User::factory()->create();
    $otherAccount = Account::create([
        'user_id' => $otherUser->id,
        'name' => 'Other User Account',
        'account_type' => 'bank',
        'currency_code' => 'USD',
        'initial_balance' => 5000,
        'current_balance' => 5000,
        'is_active' => true,
    ]);

    $transaction = Transaction::create([
        'account_id' => $otherAccount->id,
        'user_id' => $otherUser->id,
        'transaction_type' => 'expense',
        'amount' => 100,
        'currency_code' => 'USD',
        'transaction_date' => now(),
    ]);

    $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
        'amount' => 200,
    ])->assertForbidden();
});

test('user can reconcile transaction', function () {
    Sanctum::actingAs($this->user);

    $transaction = createTransaction();

    $this->postJson("/api/v1/finance/transactions/{$transaction->id}/reconcile")
        ->assertOk()
        ->assertJsonPath('message', 'Transaction reconciled successfully');

    $transaction->refresh();
    expect($transaction->reconciled_at)->not->toBeNull();
});

test('user can unreconcile transaction', function () {
    Sanctum::actingAs($this->user);

    $transaction = createTransaction(['reconciled_at' => now()]);

    $this->postJson("/api/v1/finance/transactions/{$transaction->id}/unreconcile")
        ->assertOk()
        ->assertJsonPath('message', 'Transaction unreconciled successfully');

    $transaction->refresh();
    expect($transaction->reconciled_at)->toBeNull();
});

test('unauthenticated user cannot update transaction', function () {
    $transaction = createTransaction();

    $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
        'amount' => 200,
    ])->assertUnauthorized();
});
