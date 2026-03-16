<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['categories.view', 'categories.create', 'categories.edit', 'categories.delete'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'categories.view',
        'categories.create',
        'categories.edit',
        'categories.delete',
    ]);
});

test('it can list all categories', function () {
    Category::factory()->count(5)->create();

    $response = $this->actingAs($this->user)
        ->getJson('/dashboard/categories');

    $response->assertStatus(200)
        ->assertJsonCount(5, 'categories')
        ->assertJsonStructure([
            'categories' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'color',
                    'icon',
                    'is_active',
                    'posts_count',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
});

test('it can create a category', function () {
    $categoryData = [
        'name' => 'Technology',
        'slug' => 'technology',
        'description' => 'Tech articles and tutorials',
        'color' => '#3b82f6',
        'icon' => 'laptop',
        'is_active' => true,
        'meta_title' => 'Technology Blog',
        'meta_description' => 'Read about technology',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/dashboard/categories', $categoryData);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Category created successfully',
            'category' => [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Tech articles and tutorials',
                'color' => '#3b82f6',
                'icon' => 'laptop',
                'is_active' => true,
            ],
        ]);

    $this->assertDatabaseHas('categories', [
        'name' => 'Technology',
        'slug' => 'technology',
    ]);
});

test('it auto generates slug if not provided', function () {
    $categoryData = [
        'name' => 'Web Development',
        'description' => 'Web dev tutorials',
        'is_active' => true,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/dashboard/categories', $categoryData);

    $response->assertStatus(201);

    $this->assertDatabaseHas('categories', [
        'name' => 'Web Development',
        'slug' => 'web-development',
    ]);
});

test('it validates required fields when creating', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/dashboard/categories', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('it validates unique slug when creating', function () {
    Category::factory()->create(['slug' => 'technology']);

    $response = $this->actingAs($this->user)
        ->postJson('/dashboard/categories', [
            'name' => 'Tech',
            'slug' => 'technology',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

test('it can show a category', function () {
    $category = Category::factory()->create([
        'name' => 'Technology',
        'slug' => 'technology',
    ]);

    Post::factory()
        ->count(3)
        ->create([
            'category_id' => $category->id,
            'status' => 'published',
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/dashboard/categories/{$category->slug}");

    $response->assertStatus(200)
        ->assertJson([
            'category' => [
                'id' => $category->id,
                'name' => 'Technology',
                'slug' => 'technology',
            ],
        ])
        ->assertJsonStructure([
            'category',
            'posts' => [
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'excerpt',
                        'status',
                    ],
                ],
            ],
        ]);
});

test('it can update a category', function () {
    $category = Category::factory()->create([
        'name' => 'Technology',
        'slug' => 'technology',
    ]);

    $updateData = [
        'name' => 'Tech & Innovation',
        'slug' => 'tech-innovation',
        'description' => 'Updated description',
        'color' => '#10b981',
        'is_active' => true,
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/dashboard/categories/{$category->slug}", $updateData);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Category updated successfully',
            'category' => [
                'name' => 'Tech & Innovation',
                'slug' => 'tech-innovation',
                'description' => 'Updated description',
            ],
        ]);

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Tech & Innovation',
        'slug' => 'tech-innovation',
    ]);
});

test('it validates unique slug when updating excluding current', function () {
    $category1 = Category::factory()->create(['slug' => 'technology']);
    $category2 = Category::factory()->create(['slug' => 'design']);

    // Should allow keeping the same slug
    $response = $this->actingAs($this->user)
        ->putJson("/dashboard/categories/{$category1->slug}", [
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

    $response->assertStatus(200);

    // Should not allow using another category's slug
    $response = $this->actingAs($this->user)
        ->putJson("/dashboard/categories/{$category1->slug}", [
            'name' => 'Technology',
            'slug' => 'design',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

test('it can create hierarchical categories', function () {
    $parent = Category::factory()->create([
        'name' => 'Technology',
        'slug' => 'technology',
    ]);

    $childData = [
        'name' => 'Web Development',
        'slug' => 'web-development',
        'parent_id' => $parent->id,
        'is_active' => true,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/dashboard/categories', $childData);

    $response->assertStatus(201);

    $this->assertDatabaseHas('categories', [
        'name' => 'Web Development',
        'parent_id' => $parent->id,
    ]);
});

test('it prevents circular parent references', function () {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->user)
        ->putJson("/dashboard/categories/{$category->slug}", [
            'name' => $category->name,
            'parent_id' => $category->id,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'A category cannot be its own parent',
        ]);
});

test('it can delete a category', function () {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->user)
        ->deleteJson("/dashboard/categories/{$category->slug}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Category deleted successfully',
        ]);

    $this->assertSoftDeleted('categories', [
        'id' => $category->id,
    ]);
});

test('it prevents deleting category with posts', function () {
    $category = Category::factory()->create();

    Post::factory()->create([
        'category_id' => $category->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/dashboard/categories/{$category->slug}");

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Cannot delete category with existing posts',
        ]);

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'deleted_at' => null,
    ]);
});

test('it prevents deleting category with children', function () {
    $parent = Category::factory()->create();

    Category::factory()->create([
        'parent_id' => $parent->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/dashboard/categories/{$parent->slug}");

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Cannot delete category with child categories',
        ]);

    $this->assertDatabaseHas('categories', [
        'id' => $parent->id,
        'deleted_at' => null,
    ]);
});

test('it requires authentication for all endpoints', function () {
    $category = Category::factory()->create();

    $this->getJson('/dashboard/categories')->assertStatus(401);
    $this->postJson('/dashboard/categories', [])->assertStatus(401);
    $this->getJson("/dashboard/categories/{$category->slug}")->assertStatus(401);
    $this->putJson("/dashboard/categories/{$category->slug}", [])->assertStatus(401);
    $this->deleteJson("/dashboard/categories/{$category->slug}")->assertStatus(401);
});
