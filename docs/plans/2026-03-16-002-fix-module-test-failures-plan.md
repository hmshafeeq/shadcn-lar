---
title: "fix: Module test failures (Blog, Ecommerce, Permission)"
type: fix
status: completed
date: 2026-03-16
---

# fix: Module test failures (Blog, Ecommerce, Permission)

11 test failures across 3 modules. Two root causes: Inertia component naming mismatch (5 tests) and missing `newFactory()` methods (6 tests).

## Problem Statement

After dependency upgrades (Inertia v3, Tailwind v4, Zod v4), running `php artisan test` reveals 11 failures in Blog, Ecommerce, and Permission modules. These are pre-existing issues unmasked by the upgrade, not regressions.

## Fixes

### Fix 1: Inertia Component Naming — Blog PostControllerTest (4 failures)

Controllers render with `Blog::` prefix; tests assert without it.

**File:** `Modules/Blog/Tests/Feature/PostControllerTest.php`

| Line | Current | Fix |
|------|---------|-----|
| 37 | `->component('blog/posts')` | `->component('Blog::posts')` |
| 102 | `->component('blog/create-post')` | `->component('Blog::create-post')` |
| 210 | `->component('blog/post')` | `->component('Blog::post')` |
| 225 | `->component('blog/edit-post')` | `->component('Blog::edit-post')` |

### Fix 2: Inertia Component Naming — Permission RoleControllerTest (1 failure)

**File:** `Modules/Permission/Tests/Feature/RoleControllerTest.php`

| Line | Current | Fix |
|------|---------|-----|
| 31 | `->component('roles/index')` | `->component('Permission::roles/index')` |

### Fix 3: Missing `newFactory()` — Ecommerce Models (6 failures)

Ecommerce models use `HasFactory` trait but don't override `newFactory()`. Laravel can't resolve factory namespace for module models without it.

**Pattern from working Blog module (`Modules/Blog/Models/Post.php:78-82`):**

```php
protected static function newFactory(): PostFactory
{
    return PostFactory::new();
}
```

**Files to fix (add `newFactory()` + factory import):**

| Model File | Factory Class |
|------------|--------------|
| `Modules/Ecommerce/Models/Product.php` | `Modules\Ecommerce\Database\Factories\ProductFactory` |
| `Modules/Ecommerce/Models/ProductCategory.php` | `Modules\Ecommerce\Database\Factories\ProductCategoryFactory` |
| `Modules/Ecommerce/Models/ProductTag.php` | `Modules\Ecommerce\Database\Factories\ProductTagFactory` |
| `Modules/Ecommerce/Models/Order.php` | `Modules\Ecommerce\Database\Factories\OrderFactory` |
| `Modules/Ecommerce/Models/OrderItem.php` | `Modules\Ecommerce\Database\Factories\OrderItemFactory` |

Also check Ecommerce test assertions for Inertia component naming:

**File:** `Modules/Ecommerce/Tests/Feature/ProductControllerTest.php`
- Verify component assertions use `Ecommerce::` prefix (e.g., `Ecommerce::products/index`) matching controller rendering

## Acceptance Criteria

- [x] Ecommerce tests pass (6 failures → 0)
- [x] Permission tests pass (1 failure → 0)
- [x] Blog PostControllerTest Inertia assertions fixed (4 failures → 0)
- [x] Blog Category/Tag/Post tests pass in isolation (50/50)
- [x] No regressions in passing tests (218 passed, 4 skipped, 0 failures excluding Blog module)
- [ ] Blog module tests fail in full suite due to pre-existing module migration loading issue (not related to dependency upgrades)

## Sources

- Blog controller: `Modules/Blog/Http/Controllers/PostController.php` — uses `Blog::` prefix
- Permission controller: `Modules/Permission/Http/Controllers/RoleController.php:36` — uses `Permission::` prefix
- Working factory pattern: `Modules/Blog/Models/Post.php:78-82`
- Ecommerce factories exist at: `Modules/Ecommerce/database/factories/`
