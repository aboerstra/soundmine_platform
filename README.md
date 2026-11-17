# Soundmine Platform

Commercial customer and licensing platform for Soundmine audio products. The first product is **Rhythm Conductor**, a JUCE VST3 plugin for macOS and Windows. The architecture supports multiple products from the start.

This is a **foundation**, with no payments, plugin activation, certificate issuance or production signing. No production credentials are included. Nothing has been deployed.

The repository originally contained only a one-line README at `abc21b0`. Work is isolated on `feature/laravel-foundation` for review before merging into `main`.

## Stack and structure

- Laravel 13, PHP 8.5, PostgreSQL 17
- Filament 5 administration and Livewire 4 customer portal
- PHPUnit 12 and Laravel Pint; Vite 8 frontend tooling
- Exact PHP and frontend dependencies in `composer.lock` and `package-lock.json`

```text
app/Domain/
  Customers/               Customer identity and ownership
  Catalog/                 Product catalog
  Orders/                  Orders and purchase snapshots
  Licensing/               Product license grants and state
  Entitlements/            Per-license rights
  Activations/             Installation allocation records
  OfflineAuthorization/    Challenge and certificate records
app/Livewire/Portal/        Customer sign-in and read-only license view
app/Providers/Filament/     Restricted administration shell
routes/api.php             Versioned status API only
```

See [architecture and phased implementation](docs/architecture.md) for the entity diagram, boundaries, proposed activation API, Ed25519 certificate design and offline flow. Proposed workflows are clearly distinguished from implemented code.

## Local setup with Docker

Requires Docker Compose v2 and Node.js 22.12+ (Node 24 recommended). The development PHP image supplies Composer, intl, PDO PostgreSQL and sodium. Its base image is pinned; it is tooling, not a production runtime.

```sh
cp .env.example .env
docker compose build app
docker compose up -d db
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed
npm ci
npm run build
docker compose up -d app
```

Open <http://localhost:8000>. `/portal` requires sign-in; `/admin` has its own Filament sign-in. There is no registration or default user. The product seed is idempotent and creates only the inactive Rhythm Conductor entry. No licenses, orders, keys or administrators are seeded.

`.env` is ignored. The example PostgreSQL password is intentionally for local development only. The generated `APP_KEY` is a local Laravel encryption/session key; it is not an Ed25519 signing key. Keep all real secrets out of source control.

To explore with a local account, use `docker compose run --rm app php artisan tinker` to create a User with your own chosen local credentials and a related Customer. For example, after assigning your User to `$user`, run `$user->customer()->create(['display_name' => 'Local tester']);`. Admin access requires the separate, deliberate assignment `$user->forceFill(['is_admin' => true])->save();`. No account creation happens during setup.

PostgreSQL listens on loopback port 5432, and the app listens on loopback port 8000. Compose initializes separate `soundmine` and `soundmine_test` databases on a **new** volume. If using an existing volume without the test database, create it explicitly:

```sh
docker compose exec db psql -U soundmine -d soundmine -c 'CREATE DATABASE soundmine_test;'
```

On Linux, if Docker-created files need to use your host ownership, run one-off app commands with `--user "$(id -u):$(id -g)"` and a writable `COMPOSER_HOME`, as appropriate for your Docker configuration. In managed environments with an intercepting HTTPS proxy, mount the environment CA as a temporary build secret/use the environment's prescribed CA configuration; keep certificate verification enabled. Do not commit environment trust material.

For a host PHP installation, use PHP 8.5+ with Laravel's standard extensions plus `intl`, `pdo_pgsql` and `sodium`, run `composer install`, and set `DB_HOST=127.0.0.1` in your ignored `.env`. Use the same separate PostgreSQL test database. Set test database host/credentials through environment variables if they differ from the Compose defaults.

## Laravel Boost development tooling

Laravel Boost 2 is installed in `require-dev`. Its generated `AGENTS.md` and five Codex skills under `.agents/skills` provide Laravel, Livewire, Tailwind and testing guidance. Our durable project conventions live in `.ai/guidelines/soundmine.md`; edit that source rather than the generated Boost block. `boost.json` records the selected agent/features. Laravel Cloud, Sail and Nightwatch integrations are disabled.

The generated `.codex/config.toml` starts the stdio MCP server through `bin/boost-php`. This launcher uses PHP when available (including tool subprocesses inside the container), otherwise runs PHP in the existing Docker Compose app runtime. Docker must be available; complete local setup and migrations first. With host PHP, set database networking as described above.

Some Codex clients require project trust or explicit MCP registration before loading project configuration. If Boost is absent from `codex mcp list`, run this from the project root in your own writable Codex configuration:

```sh
codex mcp add laravel-boost -- "$(pwd)/bin/boost-php" artisan boost:mcp
codex mcp list
```

Restart the coding-agent session after registering the server. Managed sessions may not permit global configuration changes or dynamically attaching new MCP servers; repository configuration alone does not guarantee that the current session exposes Boost tools. In this cloud environment, we verified the Docker launcher directly with MCP initialization, tool discovery, PostgreSQL schema inspection and version-specific documentation search; automatic discovery in the already-running agent session was not available.

To regenerate guidance and skills after dependency updates:

```sh
docker compose run --rm app php artisan boost:update --no-discover --no-interaction
```

Review regenerated files before committing them. Only the portable MCP configuration is tracked under `.codex`; other local Codex state remains ignored. Boost is enabled only with `APP_ENV=local` and can be turned off using `BOOST_ENABLED=false`. Browser-log injection is disabled. Production installs should use `composer install --no-dev`; Boost remains disabled outside local development even if dev packages are accidentally present. This adds no public MCP HTTP endpoint, production credential or licensing functionality.

## Validation

```sh
docker compose run --rm app composer validate --strict
docker compose run --rm app composer check-platform-reqs
docker compose run --rm app composer test
docker compose run --rm app vendor/bin/pint --test
npm run build
```

Tests use PostgreSQL, not SQLite, to exercise JSONB, foreign keys, uniqueness and CHECK constraints. `phpunit.xml` selects `soundmine_test`; **never point tests at a database containing real data**. Environment overrides can supply a different isolated test database. No signing credentials or Stripe account are needed.

Tests cover multi-product ownership, purchase snapshots, entitlement and installation uniqueness, offline request digest uniqueness, certificate relationships, invalid amounts/dates, deletion protection, seed repeatability, customer isolation, restricted administration, session login/logout, throttling and absent licensing mutation routes. Certificate fixtures are inert strings, not signed licenses.

GitHub Actions runs dependency validation, formatting, PostgreSQL tests and the frontend build on pushes and pull requests. It has no deployment steps or production secrets.

## Current limits

The admin panel has a dashboard only; business management resources remain future work. The portal supports basic sign-in and a read-only license list; account invitations, recovery and verification are not implemented. Domain models and migrations establish storage, not a production licensing engine. No cross-record issuance actions, activation limits enforcement, real signing or offline authorization workflows exist yet. See the architecture document for the next phases.

Stop local services with `docker compose down`; this retains PostgreSQL data. Review the feature branch diff before merging. No merge or deployment is part of this work.
