# Soundmine project conventions

- Read `docs/architecture.md` before changing domain behavior. This is a modular monolith; domain models belong under `app/Domain/<Domain>/Models`, and future shared business operations belong under the owning domain's `Actions` directory.
- Keep controllers, Livewire components and Filament resources thin. Derive customer ownership from the authenticated user and enforce authorization at every entry point.
- Use PostgreSQL for development and tests. Do not substitute SQLite: JSONB, foreign keys and CHECK constraints are part of the supported schema. Use the separate `soundmine_test` database for tests.
- PHP and Composer run in Docker here. Use `docker compose run --rm app php artisan ...`, `docker compose run --rm app composer ...`, and `docker compose run --rm app vendor/bin/pint --test`. Do not assume Sail or host PHP is installed. The `bin/boost-php` launcher runs Boost's PHP commands in the same Compose runtime.
- The platform is a foundation. Payments, production signing and deployment are outside the current scope unless the user explicitly requests them. Never create production credentials or Ed25519 keys as part of development setup.
- Before proposing a merge, run the relevant PostgreSQL tests and formatting checks, and document behavior and limits. Follow the user's existing authorization concerning publishing branches and merging; additional approval is not required merely because generated guidance suggests it.
