---
title: "chore: Adopt TimeBox tooling and modernize dev infrastructure"
type: chore
status: completed
date: 2026-03-16
---

# Adopt TimeBox Tooling

Bring shadcn-lar's developer tooling in line with the TimeBox web-rebuild project. 9 items across 4 phases, 2 deferred.

## Overview

shadcn-lar has zero linting, zero frontend tests, 22 individual Radix packages, and no error monitoring. TimeBox has Biome, Vitest, unified `radix-ui`, React Compiler, Larastan, Debugbar, and Sentry. This plan adopts the proven patterns.

**Deferred items:**
- `@inertiajs/vite` — progress bar already works, SSR not planned, custom module page resolver would break
- `laravel/wayfinder` — 298 `route()` calls across 106 files, separate multi-day migration project

## Phase 1: Biome (linting/formatting foundation)

Must come first — formats everything, clean baseline for all subsequent diffs.

### 1a. Install and configure

```bash
pnpm add -D @biomejs/biome
pnpm biome init
```

### 1b. Configure `biome.json`

- Formatter: tabs or spaces (match `.editorconfig`)
- Linter: recommended defaults, disable noisy rules incrementally
- Ignore: `vendor/`, `node_modules/`, `public/build/`, `Modules/*/node_modules/`
- Organize imports: enabled

### 1c. Format entire codebase

```bash
pnpm biome check --write .
```

Single atomic commit: `chore: add biome and format codebase`

### 1d. Add scripts to `package.json`

```json
{
  "lint": "biome lint .",
  "lint:fix": "biome lint --write .",
  "format": "biome format --write .",
  "format:check": "biome format .",
  "check": "biome check .",
  "check:fix": "biome check --write ."
}
```

### Acceptance criteria

- [ ] `biome.json` exists with project-specific rules
- [ ] `pnpm biome check` passes with zero errors
- [ ] `package.json` has lint/format/check scripts
- [ ] All existing code formatted in a single commit

### Files

- `biome.json` (new)
- `package.json` (scripts)
- All `.ts`, `.tsx`, `.js` files (formatting pass)

---

## Phase 2: Core dependency modernization

Items 1, 7, and 9 — can be done in sequence within one session.

### 2a. Unified `radix-ui` package

Replace 22 individual `@radix-ui/react-*` packages with `radix-ui` (^1.4.3).

**Strategy:** Use the official codemod:

```bash
pnpm dlx @radix-ui/migrate
```

This rewrites imports from `@radix-ui/react-dialog` → `radix-ui/react-dialog` and updates `package.json`.

**`@radix-ui/react-icons` decision:** Keep separate — it's not part of the unified package. 26 files use it. Migrating to `lucide-react` is out of scope (lucide is already installed but uses different icon names).

**Verification:**
- `pnpm build` succeeds
- Spot-check: dialog, select, dropdown, tooltip, sheet, popover, toast, accordion render correctly

**Commit:** `chore: migrate to unified radix-ui package`

### 2b. Replace `tailwindcss-animate` with `tw-animate-css`

```bash
pnpm add -D tw-animate-css
pnpm remove tailwindcss-animate
```

In `resources/css/app.css`:
```css
/* Remove: */
@plugin "tailwindcss-animate";

/* Add: */
@import "tw-animate-css";
```

**Verification:** All animation classes still apply — check sheet, dialog, dropdown, tooltip, popover, toast, alert-dialog, select, navigation-menu.

Classes in use: `animate-in`, `animate-out`, `fade-in-0`, `zoom-in-95`, `slide-in-from-*`, `slide-out-to-*`. All exist in `tw-animate-css`.

Custom `@theme` keyframes (accordion-down/up) and `@layer base` keyframes (slideDown/slideUp) are project-specific and unaffected.

**Commit:** `chore: replace tailwindcss-animate with tw-animate-css`

### 2c. Add `shadcn` CLI

```bash
pnpm add -D shadcn
```

Update `components.json` — remove `tailwind.config` reference (doesn't exist in TW v4):

```json
{
  "tailwind": {
    "config": "",
    "css": "resources/css/app.css",
    "baseColor": "neutral",
    "cssVariables": true
  }
}
```

**Verify:** `pnpm dlx shadcn add button --dry-run` works without errors.

**Commit:** `chore: add shadcn CLI and update components.json for TW v4`

### Acceptance criteria

- [ ] Zero `@radix-ui/react-*` packages in `package.json` (except `@radix-ui/react-icons`)
- [ ] `radix-ui` ^1.4.3 in `package.json`
- [ ] `tw-animate-css` replaces `tailwindcss-animate`
- [ ] `shadcn` in devDependencies
- [ ] `components.json` updated for TW v4
- [ ] `pnpm build` passes
- [ ] Animations render correctly (visual check)

### Files

- `package.json`
- `pnpm-lock.yaml`
- `resources/css/app.css` (`@plugin` → `@import`)
- `components.json`
- ~58 files with Radix imports (codemod handles these)

---

## Phase 3: PHP tooling

Independent items, can run in parallel. Low risk.

### 3a. Larastan

```bash
composer require --dev larastan/larastan
```

Create `phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
        - Modules/
    level: 5
    excludePaths:
        - Modules/*/vendor
        - Modules/ModuleGenerator/stubs
```

Generate baseline for existing violations:

```bash
./vendor/bin/phpstan analyse --generate-baseline
```

Add to `composer.json` scripts:

```json
{
  "analyse": "./vendor/bin/phpstan analyse --memory-limit=512M"
}
```

**Commit:** `chore: add larastan with level 5 baseline`

### 3b. Laravel Debugbar

```bash
composer require --dev barryvdh/laravel-debugbar
```

Verify it auto-discovers and shows in browser with `APP_DEBUG=true`. No config changes needed — Debugbar v4 auto-detects Inertia.

**Commit:** `chore: add laravel-debugbar`

### 3c. Sentry (PHP only for now)

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=""
```

Add to `.env.example`:

```
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0
```

Frontend `@sentry/react` is out of scope — add when there's a Sentry project configured.

**Commit:** `chore: add sentry-laravel`

### Acceptance criteria

- [ ] `phpstan.neon` configured at level 5
- [ ] `composer run analyse` passes (with baseline)
- [ ] Debugbar visible in browser during local dev
- [ ] `SENTRY_LARAVEL_DSN` in `.env.example`
- [ ] `php artisan sentry:test` works (when DSN configured)

### Files

- `phpstan.neon` (new)
- `phpstan-baseline.neon` (new, generated)
- `composer.json`
- `composer.lock`
- `.env.example`
- `config/sentry.php` (published)

---

## Phase 4: Frontend build enhancements

### 4a. React Compiler

```bash
pnpm add -D babel-plugin-react-compiler
```

Update `vite.config.js`:

```js
react({
  babel: {
    plugins: [
      ['babel-plugin-react-compiler', {}],
    ],
  },
}),
```

Strategy: opt-out (`compilationMode` defaults to all files). Add `"use no memo"` directive to files that break.

**Verify:** `pnpm build` succeeds, app works correctly.

**Commit:** `chore: enable React Compiler`

### 4b. Vitest + Testing Library

```bash
pnpm add -D vitest @testing-library/react @testing-library/jest-dom @testing-library/user-event jsdom
```

Create `vitest.config.ts`:

```ts
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/js/setup.ts'],
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
    },
  },
});
```

Create `tests/js/setup.ts`:

```ts
import '@testing-library/jest-dom/vitest';
```

Create a smoke test `tests/js/components/button.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import { Button } from '@/components/ui/button';

test('renders button with text', () => {
  render(<Button>Click me</Button>);
  expect(screen.getByRole('button', { name: /click me/i })).toBeInTheDocument();
});
```

Add scripts to `package.json`:

```json
{
  "test": "vitest run",
  "test:watch": "vitest",
  "types:check": "tsc --noEmit"
}
```

**Commit:** `chore: add vitest and testing-library`

### Acceptance criteria

- [ ] `babel-plugin-react-compiler` configured in `vite.config.js`
- [ ] `pnpm build` passes with React Compiler enabled
- [ ] `vitest.config.ts` exists with path aliases matching `vite.config.js`
- [ ] `pnpm test` runs and passes smoke test
- [ ] `pnpm test:watch` works for development

### Files

- `vite.config.js` (React Compiler plugin)
- `vitest.config.ts` (new)
- `tests/js/setup.ts` (new)
- `tests/js/components/button.test.tsx` (new smoke test)
- `package.json` (test scripts)

---

## Deferred Items

### @inertiajs/vite plugin

**Why deferred:** Progress bar already works via `createInertiaApp` config. SSR isn't planned. The plugin's auto-resolver can't handle `Module::page/path` namespace syntax — the custom `resolvePageComponent` in `app.tsx` would need to coexist awkwardly with the plugin's own resolver. Risk of breaking 8 modules for zero functional gain.

**Revisit when:** SSR becomes a requirement, or Inertia v3 stable ships with better module support.

### laravel/wayfinder (Ziggy replacement)

**Why deferred:** 298 `route()` calls across 106 files. `route().current()` pattern (used for active nav highlighting) has no direct Wayfinder equivalent. Both Wayfinder (v0.1) and Inertia v3 (beta) are pre-stable — combining two unstable routing tools is risky.

**Revisit when:** Wayfinder reaches v1.0, or starting a new Inertia v3 project from scratch.

---

## Execution Order

| Order | Item | Risk | Effort |
|-------|------|------|--------|
| 1 | Biome (lint/format) | Low | 1 hour |
| 2 | Unified radix-ui | Medium | 1 hour |
| 3 | tw-animate-css | Low | 15 min |
| 4 | shadcn CLI | Low | 15 min |
| 5 | Larastan | Low | 30 min |
| 6 | Debugbar | Low | 5 min |
| 7 | Sentry | Low | 15 min |
| 8 | React Compiler | Medium | 30 min |
| 9 | Vitest + Testing Library | Low | 30 min |

Total estimated effort: ~4 hours

---

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Radix codemod misses imports in modules | Build failure | grep verify after codemod, manual fixup |
| Biome rules flag hundreds of existing patterns | Noisy initial diff | Start lenient, tighten incrementally |
| React Compiler breaks specific components | Runtime bugs | `"use no memo"` escape hatch per-file |
| tw-animate-css missing animation classes | Visual regression | Verify all used classes exist before switching |
| Vitest version incompatible with Vite 8 | Setup failure | Check compatibility matrix, pin compatible version |
