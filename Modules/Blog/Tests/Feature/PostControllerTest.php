<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['posts.view', 'posts.create', 'posts.edit', 'posts.delete'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'posts.view',
        'posts.create',
        'posts.edit',
        'posts.delete',
    ]);

    Storage::fake('public');
});

test('it can list all posts', function () {
    Post::factory()->count(5)->create();

    $response = $this->actingAs($this->user)
        ->get('/dashboard/posts');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('blog/posts')
            ->has('posts.data', 5)
        );
});

test('it can filter posts by status', function () {
    Post::factory()->count(3)->create(['status' => 'published']);
    Post::factory()->count(2)->create(['status' => 'draft']);

    $response = $this->actingAs($this->user)
        ->get('/dashboard/posts?status=published');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->has('posts.data', 3)
        );
});

test('it can filter posts by category', function () {
    $category = Category::factory()->create();

    Post::factory()->count(3)->create(['category_id' => $category->id]);
    Post::factory()->count(2)->create();

    $response = $this->actingAs($this->user)
        ->get("/dashboard/posts?category={$category->id}");

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->has('posts.data', 3)
        );
});

test('it can filter posts by tag', function () {
    $tag = Tag::factory()->create();
    $posts = Post::factory()->count(3)->create();

    $posts->each(fn ($post) => $post->tags()->attach($tag));

    Post::factory()->count(2)->create();

    $response = $this->actingAs($this->user)
        ->get("/dashboard/posts?tag={$tag->id}");

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->has('posts.data', 3)
        );
});

test('it can search posts', function () {
    Post::factory()->create(['title' => 'Laravel Tutorial']);
    Post::factory()->create(['title' => 'Vue.js Guide']);
    Post::factory()->create(['content' => 'Laravel is awesome']);

    $response = $this->actingAs($this->user)
        ->get('/dashboard/posts?search=Laravel');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->has('posts.data', 2)
        );
});

test('it can show create post form', function () {
    $response = $this->actingAs($this->user)
        ->get('/dashboard/posts/create');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('blog/create-post')
            ->has('categories')
            ->has('tags')
        );
});

test('it can create a post', function () {
    $category = Category::factory()->create();
    $tags = Tag::factory()->count(3)->create();

    $postData = [
        'title' => 'My First Blog Post',
        'slug' => 'my-first-blog-post',
        'content' => 'This is the content of my blog post.',
        'excerpt' => 'Short excerpt',
        'status' => 'draft',
        'is_featured' => false,
        'category_id' => $category->id,
        'tag_ids' => $tags->pluck('id')->toArray(),
        'meta_title' => 'My Blog Post',
        'meta_description' => 'Meta description',
    ];

    $response = $this->actingAs($this->user)
        ->post('/dashboard/posts', $postData);

    $response->assertRedirect('/dashboard/posts');

    $this->assertDatabaseHas('posts', [
        'title' => 'My First Blog Post',
        'slug' => 'my-first-blog-post',
        'category_id' => $category->id,
    ]);

    $post = Post::where('slug', 'my-first-blog-post')->first();
    expect($post->tags()->count())->toBe(3);
});

test('it auto generates slug if not provided', function () {
    $postData = [
        'title' => 'My Blog Post',
        'content' => 'Content here',
        'status' => 'draft',
        'is_featured' => false,
    ];

    $response = $this->actingAs($this->user)
        ->post('/dashboard/posts', $postData);

    $response->assertRedirect();

    $this->assertDatabaseHas('posts', [
        'title' => 'My Blog Post',
        'slug' => 'my-blog-post',
    ]);
});

test('it can upload featured image', function () {
    $file = UploadedFile::fake()->image('featured.jpg');

    $postData = [
        'title' => 'Post with Image',
        'content' => 'Content',
        'status' => 'draft',
        'is_featured' => false,
        'featured_image' => $file,
    ];

    $response = $this->actingAs($this->user)
        ->post('/dashboard/posts', $postData);

    $response->assertRedirect();

    $post = Post::where('title', 'Post with Image')->first();
    expect($post->getFirstMedia('featured_image'))->not->toBeNull();
});

test('it validates required fields when creating', function () {
    $response = $this->actingAs($this->user)
        ->post('/dashboard/posts', []);

    $response->assertSessionHasErrors(['title', 'content', 'status']);
});

test('it validates unique slug', function () {
    Post::factory()->create(['slug' => 'my-post']);

    $postData = [
        'title' => 'Another Post',
        'slug' => 'my-post',
        'content' => 'Content',
        'status' => 'draft',
        'is_featured' => false,
    ];

    $response = $this->actingAs($this->user)
        ->post('/dashboard/posts', $postData);

    $response->assertSessionHasErrors(['slug']);
});

test('it can show a post', function () {
    $post = Post::factory()->create();

    $response = $this->actingAs($this->user)
        ->get("/dashboard/posts/{$post->slug}");

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('blog/post')
            ->has('post', fn ($prop) => $prop->where('id', $post->id)
                ->where('slug', $post->slug)
                ->etc()
            )
        );
});

test('it can show edit post form', function () {
    $post = Post::factory()->create();

    $response = $this->actingAs($this->user)
        ->get("/dashboard/posts/{$post->slug}/edit");

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('blog/edit-post')
            ->has('post')
            ->has('categories')
            ->has('tags')
        );
});

test('it can update a post', function () {
    $post = Post::factory()->create([
        'title' => 'Original Title',
        'slug' => 'original-title',
    ]);

    $updateData = [
        'title' => 'Updated Title',
        'slug' => 'updated-title',
        'content' => 'Updated content',
        'excerpt' => 'Updated excerpt',
        'status' => 'published',
        'is_featured' => true,
        '_method' => 'PUT',
    ];

    $response = $this->actingAs($this->user)
        ->post("/dashboard/posts/{$post->slug}", $updateData);

    $response->assertRedirect();

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'title' => 'Updated Title',
        'slug' => 'updated-title',
        'status' => 'published',
        'is_featured' => true,
    ]);
});

test('it can update post category', function () {
    $category1 = Category::factory()->create();
    $category2 = Category::factory()->create();

    $post = Post::factory()->create([
        'category_id' => $category1->id,
        'status' => 'draft',
    ]);

    $updateData = [
        'title' => $post->title,
        'content' => $post->content,
        'status' => $post->status,
        'is_featured' => $post->is_featured,
        'category_id' => $category2->id,
        '_method' => 'PUT',
    ];

    $response = $this->actingAs($this->user)
        ->post("/dashboard/posts/{$post->slug}", $updateData);

    $response->assertRedirect();

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'category_id' => $category2->id,
    ]);
});

test('it can update post tags', function () {
    $post = Post::factory()->create(['status' => 'draft']);
    $oldTags = Tag::factory()->count(2)->create();
    $post->tags()->attach($oldTags);

    $newTags = Tag::factory()->count(3)->create();

    $updateData = [
        'title' => $post->title,
        'content' => $post->content,
        'status' => $post->status,
        'is_featured' => $post->is_featured,
        'tag_ids' => $newTags->pluck('id')->toArray(),
        '_method' => 'PUT',
    ];

    $response = $this->actingAs($this->user)
        ->post("/dashboard/posts/{$post->slug}", $updateData);

    $response->assertRedirect();

    $post->refresh();
    expect($post->tags()->count())->toBe(3)
        ->and($post->tags->contains($newTags[0]))->toBeTrue();
});

test('it can remove featured image', function () {
    $post = Post::factory()->create(['status' => 'draft']);
    $file = UploadedFile::fake()->image('featured.jpg');
    $post->addMedia($file)->toMediaCollection('featured_image');

    $updateData = [
        'title' => $post->title,
        'content' => $post->content,
        'status' => $post->status,
        'is_featured' => $post->is_featured,
        'remove_featured_image' => true,
        '_method' => 'PUT',
    ];

    $response = $this->actingAs($this->user)
        ->post("/dashboard/posts/{$post->slug}", $updateData);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    $post->refresh();
    expect($post->getMedia('featured_image'))->toHaveCount(0);
});

test('it can delete a post', function () {
    $post = Post::factory()->create();

    $response = $this->actingAs($this->user)
        ->delete("/dashboard/posts/{$post->slug}");

    $response->assertRedirect('/dashboard/posts');

    $this->assertSoftDeleted('posts', [
        'id' => $post->id,
    ]);
});

test('it can publish a draft post', function () {
    $post = Post::factory()->create(['status' => 'draft']);

    $updateData = [
        'title' => $post->title,
        'content' => $post->content,
        'status' => 'published',
        'is_featured' => $post->is_featured,
        '_method' => 'PUT',
    ];

    $response = $this->actingAs($this->user)
        ->post("/dashboard/posts/{$post->slug}", $updateData);

    $response->assertRedirect();

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'status' => 'published',
    ]);

    $post->refresh();
    expect($post->published_at)->not->toBeNull();
});

test('it can schedule a post', function () {
    $futureDate = now()->addWeek();

    $postData = [
        'title' => 'Scheduled Post',
        'content' => 'Content',
        'status' => 'scheduled',
        'is_featured' => false,
        'published_at' => $futureDate->format('Y-m-d\TH:i'),
    ];

    $response = $this->actingAs($this->user)
        ->post('/dashboard/posts', $postData);

    $response->assertRedirect();

    $this->assertDatabaseHas('posts', [
        'title' => 'Scheduled Post',
        'status' => 'scheduled',
    ]);

    $post = Post::where('title', 'Scheduled Post')->first();
    expect($post->published_at->format('Y-m-d H:i'))->toBe($futureDate->format('Y-m-d H:i'));
});

test('it requires authentication for all endpoints', function () {
    $post = Post::factory()->create();

    $this->get('/dashboard/posts')->assertRedirect('/login');
    $this->get('/dashboard/posts/create')->assertRedirect('/login');
    $this->post('/dashboard/posts', [])->assertRedirect('/login');
    $this->get("/dashboard/posts/{$post->slug}")->assertRedirect('/login');
    $this->get("/dashboard/posts/{$post->slug}/edit")->assertRedirect('/login');
    $this->post("/dashboard/posts/{$post->slug}", ['_method' => 'PUT'])->assertRedirect('/login');
    $this->delete("/dashboard/posts/{$post->slug}")->assertRedirect('/login');
});
