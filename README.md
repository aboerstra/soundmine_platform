# TheSoundMine Platform

Commercial customer and licensing platform for TheSoundMine audio products. The first product is **TheSoundMine Pattern Conductor**, a JUCE VST3 plugin for macOS and Windows. The architecture supports multiple products from the start. The brand website is [thesoundmine.com](https://thesoundmine.com). See the [brand kit](docs/brand.md) for naming and assets.

This is an **online activation preview**, with no payments, certificate issuance or production signing. No production credentials are included. Nothing has been deployed.

The repository originally contained only a one-line README at `abc21b0`. The foundation, Boost tooling, invite-based customer onboarding and audited manual license management are merged. This phase adds product-scoped online activation and entitlement APIs.

## Stack and structure

- Laravel 13, PHP 8.5, PostgreSQL 17; Laravel Socialite 5 for optional Google sign-in
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
  Activations/             Installation allocation, authorization codes and scoped credentials
  OfflineAuthorization/    Challenge and certificate records
app/Livewire/Portal/        Customer sign-in, licenses and installation authorization
app/Providers/Filament/     Restricted administration shell
routes/api.php             Versioned status and product-scoped activation API
```

See [architecture and phased implementation](docs/architecture.md) for the entity diagram, boundaries, online activation boundaries, proposed Ed25519 certificate design and offline flow. See the [activation API contract](docs/activation-api.md) for client integration. Proposed workflows are clearly distinguished from implemented code.

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

Open <http://localhost:8000>. `/portal` requires sign-in; `/admin` has its own Filament sign-in. There is no public self-registration or default user. Administrators invite customers through Filament. The product seed is idempotent and creates only the inactive Pattern Conductor entry. No licenses, orders, keys or administrators are seeded. Manual license grants require an administrator after setup.

`.env` is ignored. The example PostgreSQL password is intentionally for local development only. The generated `APP_KEY` is a local Laravel encryption/session key; it is not an Ed25519 signing key. Keep all real secrets out of source control.

To explore with a local account, use `docker compose run --rm app php artisan tinker` to create a User with your own chosen local credentials and a related Customer. For example, after assigning your User to `$user`, run `$user->customer()->create(['display_name' => 'Local tester']);`. Admin access requires the separate, deliberate assignment `$user->forceFill(['is_admin' => true])->save();`. No account creation happens during setup.

PostgreSQL listens on loopback port 5432, and the app listens on loopback port 8000. Compose initializes separate `soundmine` and `soundmine_test` databases on a **new** volume. If using an existing volume without the test database, create it explicitly:

```sh
docker compose exec db psql -U soundmine -d soundmine -c 'CREATE DATABASE soundmine_test;'
```

On Linux, if Docker-created files need to use your host ownership, run one-off app commands with `--user "$(id -u):$(id -g)"` and a writable `COMPOSER_HOME`, as appropriate for your Docker configuration. In managed environments with an intercepting HTTPS proxy, mount the environment CA as a temporary build secret/use the environment's prescribed CA configuration; keep certificate verification enabled. Do not commit environment trust material.

For a host PHP installation, use PHP 8.5+ with Laravel's standard extensions plus `intl`, `pdo_pgsql` and `sodium`, run `composer install`, and set `DB_HOST=127.0.0.1` in your ignored `.env`. Use the same separate PostgreSQL test database. Set test database host/credentials through environment variables if they differ from the Compose defaults.

## Customer and manual license workflows

1. Bootstrap a local administrator using the Tinker instructions above, explicitly assigning `is_admin`. Complete email verification using the link in the local mail log. No administrator is created by a migration or seed.
2. In `/admin/customers`, select **Invite customer**. Pending and accepted invitations are listed under **Customer invitations**. Reissuing a pending invitation invalidates its previous link and starts a new 48-hour validity period.
3. The invited person chooses a password of at least 12 characters. The email/name come from the invitation; account creation never accepts an administrator flag. Invitations are single-use and stored with a SHA-256 token digest, not the token itself. Email verification is required before portal license access.
4. Mark a product active before granting a license. In **Licenses**, choose **Grant license**, select a customer/product, set validity and device allowance, optionally enter entitlement key/value strings, and provide a reason. No order or payment is created.
5. Administrators can suspend an active license, resume a suspended license, or revoke a pending/active/suspended license. Revocation is terminal; correcting a revoked grant requires a new grant. Every operation requires a reason and records the administrator, time, state transition and grant snapshot. Review history on the license's detail page. Raw editing/deletion of licenses is not offered.
6. Verified customers use `/portal` and its license-detail links to view their own products, dates, device allowances and entitlements. Administrator audit reasons and other customers' records are not exposed. Password recovery is available from sign-in; valid reset links expire after 60 minutes and are single-use. Resetting a password rotates the remember token and invalidates existing portal sessions on their next protected request.

Mail uses Laravel's configured notification channels. The development default is `MAIL_MAILER=log`: invitation, verification and recovery emails appear in `storage/logs/laravel.log`. No external messages are sent during automated tests. Configure and verify a real mail provider separately before using these flows outside development; no mail credentials are supplied here. Notification delivery is synchronous and occurs after each business transaction. An administrator can reissue an invitation if delivery fails, and customers can request another verification/reset link. Treat local mail logs as sensitive because they contain temporary authorization links.

The new migration normalizes existing user emails and adds a case-insensitive unique index. Existing case/whitespace-equivalent duplicate accounts must be reconciled before applying it. Rollback drops invitation and audit tables and cannot restore original email casing; use a forward fix once these records contain real history.

License history is append-only through the application's model and has no edit/delete UI. It is not a tamper-proof ledger against privileged database access. Invitation acceptance and license changes use PostgreSQL row locks; license updates and audit insertion commit or roll back together. Manual entitlement values are strings in this phase; the existing JSONB storage can still display older structured values. The status API reports online activation separately from signed licensing, which remains unavailable.

## Google customer sign-in

Laravel Socialite 5 adds optional Google sign-in to the invite-only customer portal. It is disabled by default. Password login and recovery continue to work. No Google application, credential or consent screen has been created by this change.

To configure it yourself, register a **Web application** OAuth client in Google Cloud, configure its consent screen/audience and test users as required, and register your portal origin followed by `/auth/google/callback` as the exact authorized redirect URI. Use HTTPS outside local development. Put these values in your ignored environment configuration:

```dotenv
GOOGLE_AUTH_ENABLED=true
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
```

Supply your own client ID, secret and complete registered callback URI. Keep them out of Git, browser code and chat. Run the new migration and refresh Laravel's configuration cache after configuration changes. All three values and the enabled flag are required; otherwise Google buttons are hidden and OAuth endpoints return 404. Missing configuration does not disable local password login. Disconnecting a stored link remains available even when Google is disabled.

- **Existing customers:** sign in with a password, open **Account sign-in**, confirm the current password and connect the verified Google account matching the Soundmine email. A matching email never automatically links or logs into an existing account. Disconnecting also requires the current password.
- **Invited customers:** open the invitation and choose **Accept invitation with Google**. Google must confirm the invitation's exact normalized email as verified. Invitation validity is checked again after returning from Google. Existing accounts/identities are never overwritten; expired, reissued or consumed invitations cannot create accounts.
- **Returning customers:** use **Sign in with Google** after linking or accepting a Google invitation. The stable Google subject selects the existing User; subsequent Google email changes do not rewrite the Soundmine email or transfer licenses. Unlinked Google users cannot register through the login screen.

Google-created accounts are verified immediately and receive an unknown random password hash. To establish a password for recovery/disconnection, sign out and use the existing password recovery flow sent to the Soundmine email. Administrators cannot link or authenticate via these Google endpoints; Filament continues using its existing password authentication.

OAuth initiation uses CSRF-protected POST forms. Socialite checks session state and PKCE; the single-use login/link/invitation context expires after ten minutes and is bound to the initiating session (and user/password version when linking). Starting another flow replaces the previous one. Only `openid` and `email` scopes are requested. The database stores the provider and stable subject with unique `(provider, provider_subject)` and `(user_id, provider)` constraints, not Google tokens or profile photos. Invitation tokens in the temporary OAuth session are encrypted. Do not record OAuth query strings or provider responses in proxy/access logs; the application reports only exception classes for provider failures. Unlinking stops future Google sign-ins; existing browser sessions remain until sign-out, expiry or password reset.

Tests use Socialite fakes and Guzzle response fixtures, including the actual state/PKCE exchange path. A live Google consent flow still needs verification with your configured OAuth client before external use. No external Google request or email is sent by tests. Other identity providers are not implemented.

## Online activation preview

A verified customer opens their license details, enters the plugin's installation hash and platform, and generates a ten-minute authorization code. The plugin exchanges this code at the product-specific activation endpoint using an `Idempotency-Key` UUID header. The response supplies a scoped installation bearer token, current entitlements and the allocated activation ID. Read rights or release that allocation using the same product's routes. No reusable server secret belongs in a plugin.

Products share the implementation, while each license keeps its own allowance and entitlements. The same installation hash can activate multiple products independently. Product display names can change; stable slugs and UUIDs identify products. Turning product availability off also denies existing installations online entitlement access.

Tokens expire after 30 days, capped by license expiry. A fresh portal authorization rotates the credential without consuming another seat. Exact exchange retries return the same credential during the authorization's remaining validity; current license state is rechecked. PostgreSQL license row locks serialize seat allocation with license status changes. Codes and tokens are digested at rest; the retry credential is temporarily encrypted using Laravel's `APP_KEY` and excluded from model serialization.

Set `SOUNDMINE_ONLINE_ACTIVATION_ENABLED=false` to disable this preview. Configure Laravel's scheduler to run `php artisan schedule:run` each minute when operating the application; the registered hourly pruning task removes expired authorization rows and their encrypted retry credentials. For local cleanup:

```sh
docker compose run --rm app php artisan model:prune --model='App\Domain\Activations\Models\ActivationAuthorization'
```

Expired tokens do not automatically release seats. A valid credential can release its own allocation; renewing the same installation uses a new portal code. Lost-device recovery and customer device-management UI are future work. The [API contract](docs/activation-api.md) specifies installation identity, retry behavior, errors and client storage expectations. JUCE integration, signed certificates and offline use are still unimplemented.

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

Tests cover multi-product ownership, persistence constraints, invitation acceptance/reissue/expiry, verification, password recovery and session invalidation, customer isolation, administrative policies, Filament actions, license transitions and audit-failure rollback. Activation tests cover scoped credentials, validity, quota enforcement, retries, rotation, pruning and separate-process PostgreSQL races. Certificate fixtures are inert strings, not signed licenses.

GitHub Actions runs dependency validation, formatting, PostgreSQL tests and the frontend build on pushes and pull requests. It has no deployment steps or production secrets.

## Current limits

Administrators can invite customers, edit customer display names, manage product availability, and grant/suspend/resume/revoke license records. Customers can accept invitations, verify their email, recover passwords and view their own license details. Verified customers can authorize installations; product-scoped REST endpoints enforce online rights and device limits. Actual JUCE integration, Ed25519 signing, offline authorization, payments and deployment remain future phases. Administrator MFA and a full production operational review are not included.

Stop local services with `docker compose down`; this retains PostgreSQL data. Review the feature branch diff before merging. No merge or deployment is part of this work.
