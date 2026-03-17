# Laravel Shadcn Admin Starter Kit

A modern, responsive, and accessible admin dashboard starter kit built with Shadcn UI, Laravel 12, and React 19. Ships with a modular architecture, authentication, role management, and 10+ pages — ready to build on.

![alt text](public/images/shadcn-admin.png)

Inspired by [shadcn-admin](https://github.com/satnaing/shadcn-admin), adapted for Laravel + Inertia.js.

## Features

- Light/dark mode
- Responsive and accessible
- Built-in sidebar component
- Global search command
- Role & permission management
- User profile management
- Blog module (posts & categories)
- Finance, Invoice, E-commerce modules
- 10+ pages with extra custom components

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **UI** | [Shadcn UI](https://ui.shadcn.com) (Tailwind CSS v4 + Radix UI) |
| **Backend** | [Laravel](https://laravel.com/) 12.x |
| **Frontend** | [React](https://react.dev/) 19 + [TypeScript](https://www.typescriptlang.org/) |
| **Frontend Integration** | [Inertia.js](https://inertiajs.com/) v3 |
| **Build Tool** | [Vite](https://vitejs.dev/) 8 |
| **Compiler** | [React Compiler](https://react.dev/learn/react-compiler) |
| **Validation** | [Zod](https://zod.dev/) v4 |
| **Linting/Formatting** | [Biome](https://biomejs.dev/) |
| **PHP Testing** | [Pest](https://pestphp.com/) v4 |
| **JS Testing** | [Vitest](https://vitest.dev/) + Testing Library |
| **Static Analysis** | [Larastan](https://github.com/larastan/larastan) |
| **Debugging** | [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar) |
| **Error Tracking** | [Sentry](https://sentry.io/) |
| **Icons** | [Tabler Icons](https://tabler.io/icons) |

## Quick Start

1. **Clone the project**

```bash
git clone git@github.com:hmshafeeq/shadcn-lar.git
cd shadcn-lar
```

2. **Install dependencies**

```bash
composer install
pnpm install
```

3. **Set up environment**

```bash
cp .env.example .env
php artisan key:generate
```

4. **Database**

```bash
php artisan migrate
php artisan db:seed
```

5. **Start development**

```bash
composer dev
```

This single command starts the Laravel server, queue worker, log viewer, and Vite dev server concurrently. Open http://localhost:8000.

## CI/CD Guide

This project includes automated CI/CD workflows using GitHub Actions. The workflows are located in the `.github/workflows/` directory and provide continuous integration and deployment capabilities.

### Available Workflows

#### 1. Tests Workflow (`test.yml`)
Automatically runs on every push to the `main` branch and performs:

- **PHP Setup:** Uses PHP 8.2 with required extensions
- **Environment Setup:** Copies `.env.example` to `.env` and generates application key
- **Dependencies:** Installs Composer dependencies
- **Frontend Build:** Installs Node.js dependencies and builds production assets
- **Database Setup:** Creates SQLite database for testing
- **Test Execution:** Runs Pest tests (unit and feature)

#### 2. Deploy Workflow (`deploy.yml`)
Automatically deploys to production server on successful pushes to `main` branch:

- **Code Deployment:** Uses rsync to sync code to production server
- **Frontend Build:** Builds production assets before deployment
- **Dependencies:** Installs/updates Composer dependencies via Docker
- **Database Migration:** Runs Laravel migrations
- **Cache Management:** Clears and optimizes application cache
- **Docker Integration:** Restarts Docker containers for updated services

### Required Secrets

For the deployment workflow to work, configure these GitHub repository secrets:

- `PRIVATE_KEY` - SSH private key for server access
- `SSH_HOST` - Production server hostname/IP
- `SSH_USER` - SSH username for server access
- `WORK_DIR` - Application directory path on server
- `DOCKER_DIR` - Docker compose directory path on server

**Note**: Ensure your server is set up to allow SSH access using the provided private key. Public key should be added to the server's `~/.ssh/authorized_keys`. Folder permissions should allow the SSH user to read/write as needed.`.ssh` folder should have `700` permissions and `authorized_keys` file should have `600` permissions.

### Local Development Workflow

1. **Before Committing:**
   ```bash
   # Run tests locally
   composer test

   # Build frontend assets
   pnpm run build

   # Check code formatting
   pnpm run lint
   ```

2. **Push to Main:**
   - Tests workflow runs automatically
   - If tests pass and on `main` branch, deployment begins
   - Monitor workflow progress in GitHub Actions tab

### Workflow Customization

To modify the CI/CD behavior:

- **Test Configuration:** Edit `.github/workflows/test.yml`
- **Deployment Steps:** Edit `.github/workflows/deploy.yml`
- **Add Quality Checks:** Consider adding code style checks, static analysis, or security scans

## Modular Architecture

This project uses [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules) for a modular monorepo architecture. Each module is self-contained with its own controllers, models, migrations, and React frontend.

### Available Modules

| Module | Description |
|--------|-------------|
| Finance | Personal finance tracking (accounts, transactions, budgets) |
| Invoice | Invoice management |
| Permission | Roles and permissions management |
| Settings | Application settings |
| Blog | Blog posts and categories |
| Ecommerce | Products, orders, and categories |
| Notification | User notifications |

### Module Commands

#### Create a New Module

```bash
# Basic module scaffolding
php artisan module:scaffold ModuleName

# With CRUD scaffolding (model, migration, policy, controller, pages)
php artisan module:scaffold ModuleName --with-crud

# Specify entity name for CRUD
php artisan module:scaffold Inventory --with-crud --entity=Product

# Preview without creating files
php artisan module:scaffold ModuleName --dry-run
```

#### Generate a Standalone Site from Modules

Extract selected modules into a completely new Laravel project:

```bash
# Create a finance-only site
php artisan site:generate FinanceApp --modules=Finance --output=~/Projects

# Create a multi-module site
php artisan site:generate AdminPanel --modules=Finance,Settings,Permission --output=~/Projects

# Preview what would be created
php artisan site:generate TestApp --modules=Finance --dry-run
```

The `site:generate` command:
- Copies base Laravel+React project structure
- Includes only selected modules
- Updates all configuration files (composer.json, tsconfig.json, vite.config.js)
- Removes unused module references
- Shows next steps after generation

#### Enable/Disable Modules

```bash
# Enable a module
php artisan module:enable ModuleName

# Disable a module
php artisan module:disable ModuleName

# List all modules
php artisan module:list
```

### Module Structure

```
Modules/
└── ModuleName/
    ├── app/
    │   ├── Http/Controllers/
    │   ├── Models/
    │   ├── Policies/
    │   └── Providers/
    ├── config/
    ├── database/
    │   ├── migrations/
    │   └── seeders/
    ├── resources/
    │   └── js/
    │       ├── pages/
    │       └── types/
    ├── routes/
    │   ├── api.php
    │   └── web.php
    └── module.json
```

## Roadmap

- **Theme & Plugin Manager:** Easily install and manage themes and plugins to extend functionality.
- **File & Media Manager:** A powerful file and media manager for handling uploads and organizing assets.

## Credits

Originally created by [@binjuhor](https://github.com/binjuhor) — thank you for building the foundation this project is based on.

## Author

Maintained by [@hmshafeeq](https://github.com/hmshafeeq)

## License

This project is open-source and licensed under the [MIT License](https://choosealicense.com/licenses/mit/).
