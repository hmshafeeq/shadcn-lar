<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ecommerce\Models\{Product, ProductCategory};
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['products.view', 'products.create', 'products.edit', 'products.delete'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'products.view',
        'products.create',
        'products.edit',
        'products.delete',
    ]);
});

test('it can list all products', function () {
    Product::factory()->count(5)->create();

    $response = $this->actingAs($this->user)
        ->get('/dashboard/ecommerce/products');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Ecommerce::products', false)
            ->has('products.data', 5)
        );
});

test('it can create a product', function () {
    $category = ProductCategory::factory()->create();

    $productData = [
        'name' => 'Test Product',
        'description' => 'Test description',
        'price' => 99.99,
        'stock_quantity' => 10,
        'status' => 'active',
        'category_id' => $category->id,
    ];

    $response = $this->actingAs($this->user)
        ->post('/dashboard/ecommerce/products', $productData);

    $response->assertRedirect();

    $this->assertDatabaseHas('products', [
        'name' => 'Test Product',
        'price' => 99.99,
    ]);
});

test('it can show a product', function () {
    $product = Product::factory()->create();

    $response = $this->actingAs($this->user)
        ->get("/dashboard/ecommerce/products/{$product->slug}");

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Ecommerce::product', false)
            ->has('product')
        );
});

test('it can update a product', function () {
    $product = Product::factory()->create();

    $updateData = [
        'name' => 'Updated Product',
        'description' => 'Updated description',
        'price' => 149.99,
        'stock_quantity' => 20,
        'status' => 'active',
    ];

    $response = $this->actingAs($this->user)
        ->put("/dashboard/ecommerce/products/{$product->slug}", $updateData);

    $response->assertRedirect();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Product',
        'price' => 149.99,
    ]);
});

test('it can delete a product', function () {
    $product = Product::factory()->create();

    $response = $this->actingAs($this->user)
        ->delete("/dashboard/ecommerce/products/{$product->slug}");

    $response->assertRedirect();

    $this->assertSoftDeleted('products', [
        'id' => $product->id,
    ]);
});

test('it requires authentication for all endpoints', function () {
    $product = Product::factory()->create();

    $this->get('/dashboard/ecommerce/products')->assertRedirect('/login');
    $this->post('/dashboard/ecommerce/products', [])->assertRedirect('/login');
    $this->get("/dashboard/ecommerce/products/{$product->slug}")->assertRedirect('/login');
    $this->put("/dashboard/ecommerce/products/{$product->slug}", [])->assertRedirect('/login');
    $this->delete("/dashboard/ecommerce/products/{$product->slug}")->assertRedirect('/login');
});
