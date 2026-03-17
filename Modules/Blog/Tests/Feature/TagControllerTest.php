<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['tags.view', 'tags.create', 'tags.edit', 'tags.delete'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'tags.view',
        'tags.create',
        'tags.edit',
        'tags.delete',
    ]);
});

test('it can list all tags', function () {
    Tag::factory()->count(5)->create();

    $response = $this->actingAs($this->user)
        ->getJson('/dashboard/tags');

    $response->assertStatus(200)
        ->assertJsonCount(5, 'tags')
        ->assertJsonStructure([
            'tags' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'color',
                    'is_active',
                    'usage_count',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
});

test('it orders tags by usage count', function () {
    $tag1 = Tag::factory()->create(['usage_count' => 5]);
    $tag2 = Tag::factory()->create(['usage_count' => 10]);
    $tag3 = Tag::factory()->create(['usage_count' => 3]);

    $response = $this->actingAs($this->user)
        ->getJson('/dashboard/tags');

    $tags = $response->json('tags');

    expect($tags[0]['id'])->toBe($tag2->id)
        ->and($tags[1]['id'])->toBe($tag1->id)
        ->and($tags[2]['id'])->toBe($tag3->id);
});

test('it can create a tag', function () {
    $tagData = [
        'name' => 'Laravel',
        'slug' => 'laravel',
        'description' => 'Laravel framework articles',
        'color' => '#ff2d20',
        'is_active' => true,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/dashboard/tags', $tagData);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Tag created successfully',
            'tag' => [
                'name' => 'Laravel',
                'slug' => 'laravel',
                'description' => 'Laravel framework articles',
                'color' => '#ff2d20',
                'is_active' => true,
            ],
        ]);

    $this->assertDatabaseHas('tags', [
        'name' => 'Laravel',
        'slug' => 'laravel',
    ]);
});

test('it auto generates slug if not provided', function () {
    $tagData = [
        'name' => 'Vue.js',
        'description' => 'Vue framework',
        'is_active' => true,
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/dashboard/tags', $tagData);

    $response->assertStatus(201);

    $this->assertDatabaseHas('tags', [
        'name' => 'Vue.js',
        'slug' => 'vuejs',
    ]);
});

test('it validates required fields when creating', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/dashboard/tags', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('it validates unique slug when creating', function () {
    Tag::factory()->create(['slug' => 'laravel']);

    $response = $this->actingAs($this->user)
        ->postJson('/dashboard/tags', [
            'name' => 'Laravel PHP',
            'slug' => 'laravel',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

test('it can show a tag with posts', function () {
    $tag = Tag::factory()->create([
        'name' => 'Laravel',
        'slug' => 'laravel',
    ]);

    $posts = Post::factory()
        ->count(3)
        ->create(['status' => 'published']);

    $tag->posts()->attach($posts->pluck('id'));

    $response = $this->actingAs($this->user)
        ->getJson("/dashboard/tags/{$tag->slug}");

    $response->assertStatus(200)
        ->assertJson([
            'tag' => [
                'id' => $tag->id,
                'name' => 'Laravel',
                'slug' => 'laravel',
            ],
        ])
        ->assertJsonStructure([
            'tag',
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

test('it can update a tag', function () {
    $tag = Tag::factory()->create([
        'name' => 'Laravel',
        'slug' => 'laravel',
    ]);

    $updateData = [
        'name' => 'Laravel Framework',
        'slug' => 'laravel-framework',
        'description' => 'Updated description',
        'color' => '#10b981',
        'is_active' => true,
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/dashboard/tags/{$tag->slug}", $updateData);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Tag updated successfully',
            'tag' => [
                'name' => 'Laravel Framework',
                'slug' => 'laravel-framework',
                'description' => 'Updated description',
            ],
        ]);

    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'name' => 'Laravel Framework',
        'slug' => 'laravel-framework',
    ]);
});

test('it validates unique slug when updating excluding current', function () {
    $tag1 = Tag::factory()->create(['slug' => 'laravel']);
    $tag2 = Tag::factory()->create(['slug' => 'vue']);

    // Should allow keeping the same slug
    $response = $this->actingAs($this->user)
        ->putJson("/dashboard/tags/{$tag1->slug}", [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

    $response->assertStatus(200);

    // Should not allow using another tag's slug
    $response = $this->actingAs($this->user)
        ->putJson("/dashboard/tags/{$tag1->slug}", [
            'name' => 'Laravel',
            'slug' => 'vue',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

test('it can delete a tag', function () {
    $tag = Tag::factory()->create();

    $response = $this->actingAs($this->user)
        ->deleteJson("/dashboard/tags/{$tag->slug}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Tag deleted successfully',
        ]);

    $this->assertSoftDeleted('tags', [
        'id' => $tag->id,
    ]);
});

test('it prevents deleting tag with posts', function () {
    $tag = Tag::factory()->create();

    $post = Post::factory()->create();
    $tag->posts()->attach($post->id);

    $response = $this->actingAs($this->user)
        ->deleteJson("/dashboard/tags/{$tag->slug}");

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Cannot delete tag with existing posts',
        ]);

    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'deleted_at' => null,
    ]);
});

test('it can get popular tags', function () {
    $tag1 = Tag::factory()->create(['usage_count' => 50, 'is_active' => true]);
    $tag2 = Tag::factory()->create(['usage_count' => 100, 'is_active' => true]);
    $tag3 = Tag::factory()->create(['usage_count' => 25, 'is_active' => true]);
    Tag::factory()->create(['usage_count' => 75, 'is_active' => false]);

    $response = $this->actingAs($this->user)
        ->getJson('/dashboard/tags/popular');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'tags'); // Only active tags

    $tags = $response->json('tags');

    // Should be ordered by usage count descending
    expect($tags[0]['id'])->toBe($tag2->id)
        ->and($tags[1]['id'])->toBe($tag1->id)
        ->and($tags[2]['id'])->toBe($tag3->id);
});

test('it limits popular tags to 20', function () {
    Tag::factory()->count(25)->create(['is_active' => true]);

    $response = $this->actingAs($this->user)
        ->getJson('/dashboard/tags/popular');

    $response->assertStatus(200)
        ->assertJsonCount(20, 'tags');
});

test('it requires authentication for all endpoints', function () {
    $tag = Tag::factory()->create();

    $this->getJson('/dashboard/tags')->assertStatus(401);
    $this->postJson('/dashboard/tags', [])->assertStatus(401);
    $this->getJson("/dashboard/tags/{$tag->slug}")->assertStatus(401);
    $this->putJson("/dashboard/tags/{$tag->slug}", [])->assertStatus(401);
    $this->deleteJson("/dashboard/tags/{$tag->slug}")->assertStatus(401);
    $this->getJson('/dashboard/tags/popular')->assertStatus(401);
});

test('it updates usage count when tag is used', function () {
    $tag = Tag::factory()->create(['usage_count' => 0]);
    $post = Post::factory()->create();

    $tag->posts()->attach($post->id);
    $tag->updateUsageCount();

    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'usage_count' => 1,
    ]);
});
