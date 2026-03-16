<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Currency, ExchangeRate};

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    Currency::insert([
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_default' => true],
        ['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'decimal_places' => 0, 'is_default' => false],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_default' => false],
    ]);
});

function createExchangeRate(array $attributes = []): ExchangeRate
{
    return ExchangeRate::create(array_merge([
        'base_currency' => 'USD',
        'target_currency' => 'VND',
        'rate' => 24500,
        'source' => 'manual',
        'rate_date' => now(),
    ], $attributes));
}

test('user can list exchange rates', function () {
    Sanctum::actingAs($this->user);

    createExchangeRate();
    createExchangeRate(['base_currency' => 'EUR', 'target_currency' => 'USD', 'rate' => 1.08]);

    $this->getJson('/api/v1/finance/exchange-rates')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('user can filter exchange rates by base currency', function () {
    Sanctum::actingAs($this->user);

    createExchangeRate(['base_currency' => 'USD', 'target_currency' => 'VND']);
    createExchangeRate(['base_currency' => 'EUR', 'target_currency' => 'USD', 'rate' => 1.08]);

    $this->getJson('/api/v1/finance/exchange-rates?base=USD')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.base_currency', 'USD');
});

test('user can create exchange rate', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/finance/exchange-rates', [
        'base_currency' => 'USD',
        'target_currency' => 'EUR',
        'rate' => 0.92,
        'source' => 'manual',
    ])
        ->assertCreated()
        ->assertJsonPath('data.base_currency', 'USD')
        ->assertJsonPath('data.target_currency', 'EUR')
        ->assertJsonPath('data.rate', 0.92);
});

test('user cannot create exchange rate with same currencies', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/finance/exchange-rates', [
        'base_currency' => 'USD',
        'target_currency' => 'USD',
        'rate' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['target_currency']);
});

test('user can view exchange rate', function () {
    Sanctum::actingAs($this->user);

    $rate = createExchangeRate();

    $response = $this->getJson("/api/v1/finance/exchange-rates/{$rate->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $rate->id);

    expect($response->json('data.rate'))->toBe(24500);
});

test('user can delete exchange rate', function () {
    Sanctum::actingAs($this->user);

    $rate = createExchangeRate();

    $this->deleteJson("/api/v1/finance/exchange-rates/{$rate->id}")
        ->assertOk();

    $this->assertDatabaseMissing('finance_exchange_rates', ['id' => $rate->id]);
});

test('user can get latest rate', function () {
    Sanctum::actingAs($this->user);

    createExchangeRate(['rate' => 24500]);

    $response = $this->getJson('/api/v1/finance/exchange-rates/latest?base=USD&target=VND');

    $response->assertOk();
    expect($response->json('data.rate'))->toBe(24500);
});

test('user can convert currency', function () {
    Sanctum::actingAs($this->user);

    createExchangeRate(['rate' => 24500]);

    $response = $this->postJson('/api/v1/finance/exchange-rates/convert', [
        'amount' => 100,
        'from' => 'USD',
        'to' => 'VND',
    ]);

    $response->assertOk();
    expect($response->json('data.original_amount'))->toBe(100);
    expect($response->json('data.converted_amount'))->toBe(2450000);
});

test('user can list currencies', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/finance/exchange-rates/currencies')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('user can list providers', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/finance/exchange-rates/providers')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name']]]);
});

test('unauthenticated user cannot access exchange rates', function () {
    $this->getJson('/api/v1/finance/exchange-rates')
        ->assertUnauthorized();
});
