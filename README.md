# Grid Time

Grid Time aggregates motorsport race schedules and exposes them through a Symfony API and Nuxt web application.

![app.png](docs/screenshots/app.png)

Supported series:

- Formula 1, Formula 2 and Formula 3
- MotoGP, Moto2 and Moto3
- WorldSBK

## Requirements

- PHP 8.5+
- PostgreSQL
- Composer
- Bun

## Environment configuration

Use local override files for development. Symfony loads `backend/.env.local` after its committed defaults, while Nuxt reads `frontend/.env`; start the latter from `frontend/.env.example`. Do not commit either local file.

| Area        | Variables                                                                                                                              | Purpose                                                                        |
|-------------|----------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------|
| Symfony     | `APP_SECRET`, `API_KEY_PEPPER`, `CORS_ALLOW_ORIGIN`                                                                                    | Application secrets and the browser-origin allowlist.                          |
| PostgreSQL  | `DATABASE_HOST`, `DATABASE_PORT`, `DATABASE_NAME`, `DATABASE_USER`, `DATABASE_PASSWORD`, `DATABASE_SERVER_VERSION`, `DATABASE_CHARSET` | Database connection settings used by Symfony and Docker Compose.               |
| Nuxt server | `NUXT_INTERNAL_API_BASE`, `NUXT_FRONTEND_API_KEY`                                                                                      | The server-side API origin and internal API key used by Nuxt's schedule proxy. |

`NUXT_FRONTEND_API_KEY` is a server secret. Never expose it through a `NUXT_PUBLIC_*` variable or commit it to the repository.

## Container images

Both Dockerfiles use the repository root as their build context. Build them from the repository root:

```bash
docker build -f docker/build/backend/Dockerfile -t grid-time-backend .
docker build -f docker/build/frontend/Dockerfile --target production -t grid-time-frontend .
```

Runtime secrets and service URLs must be passed as container environment variables; they are intentionally excluded from the image build context.

## Docker Compose deployment

The deployment configuration and its templates live in `docker/compose/`:

- `.env` configures the domain and PostgreSQL service.
- `backend.env` configures Symfony secrets, runtime mode and CORS.
- `frontend.env` configures Nuxt's runtime mode and server-side API proxy.

Review and replace all example values before deploying. Run the following commands from `docker/compose/`, the directory containing `compose.yml`.

Pull the published images and start the application:

```bash
docker compose pull
docker compose up -d
```

Once the database is healthy, apply pending schema migrations:

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
```

Create the internal API key used exclusively by the Nuxt server:

```bash
docker compose exec backend php bin/console api-key:create "Frontend" --internal
```

The command prints the complete key once. Set that value as `NUXT_FRONTEND_API_KEY` in `frontend.env`, then recreate the frontend so it receives the new environment variable:

```bash
docker compose up -d --force-recreate frontend
```

Import all supported schedules for a season (the default year is `2026`):

```bash
docker compose exec backend php bin/console app:scrape:all --year=2026
```

Check the service status and follow logs when troubleshooting:

```bash
docker compose ps
docker compose logs -f
```

## Schedule scrapers

Run commands from `backend/`. Every scraper accepts `--year`; it defaults to `2026`.

Run every currently supported schedule scraper at once:

```bash
php bin/console app:scrape:all --year=2026
```

The combined command runs Formula 1, Formula 2, Formula 3, MotoGP, Moto2, Moto3 and WorldSBK. It continues if one series fails and returns a failure status after all series have been attempted.

| Series    | Command                                         |
|-----------|-------------------------------------------------|
| Formula 1 | `php bin/console app:scrape:f1 --year=2026`     |
| Formula 2 | `php bin/console app:scrape:f2 --year=2026`     |
| Formula 3 | `php bin/console app:scrape:f3 --year=2026`     |
| MotoGP    | `php bin/console app:scrape:motogp --year=2026` |
| Moto2     | `php bin/console app:scrape:moto2 --year=2026`  |
| Moto3     | `php bin/console app:scrape:moto3 --year=2026`  |
| WorldSBK  | `php bin/console app:scrape:wsbk --year=2026`   |

## Logging

The backend writes daily rotating logs to `backend/var/log/` and keeps 14 files per channel:

| File           | Contents                                                              |
|----------------|-----------------------------------------------------------------------|
| `app.log`      | Application and framework events                                      |
| `scraper.log`  | Schedule scrape lifecycle, source failures and import errors          |
| `security.log` | API key creation, revocation, authentication failures and rate limits |

Production records `info` and higher. Development additionally records `debug` events, including successful source requests and API-key authentication. Logs never contain API tokens, authentication headers, source response bodies, API-key labels or full client IP addresses.

## API keys

The schedule API requires an `X-API-Key` header for Series, Seasons, Events and Sessions. Keys are server-side secrets and must not be placed in browser code or committed environment files.

Create a third-party key (120 requests/minute by default):

```bash
cd backend
php bin/console api-key:create "App"
```

The complete key is printed once only. Manage keys with:

```bash
php bin/console api-key:list
php bin/console api-key:revoke <identifier>
```

Create the first-party Nuxt key with:

```bash
php bin/console api-key:create "Frontend" --internal
```

Configure it only as a Nuxt server secret:

```env
NUXT_INTERNAL_API_BASE=http://backend:8000
NUXT_FRONTEND_API_KEY=gt_live_<identifier>_<secret>
```

Nuxt serves browser schedule requests through `/_schedule`; this server-side proxy adds the key before requesting Symfony. Third-party integrations call Symfony directly with `X-API-Key`.

See [API access documentation](docs/api-access.md) for security, rate limiting and Traefik routing requirements.

## Backend setup

Create `backend/.env.local` with development-safe values for the Symfony, PostgreSQL and CORS variables listed above. Then install dependencies and apply the database schema:

```bash
cd backend
composer install
php bin/console doctrine:migrations:migrate --no-interaction
```

All schedule timestamps are stored in UTC. Scrapers are idempotent and can be run repeatedly.

## Frontend setup

Create the local Nuxt environment file, set the internal Symfony origin and an internal API key, then install dependencies:

```bash
cd frontend
cp .env.example .env
# Set NUXT_INTERNAL_API_BASE and NUXT_FRONTEND_API_KEY in .env.
bun install
```

## Verification

Validate the Docker Compose configuration before deploying:

```bash
cd docker/compose
docker compose config --quiet
```

Run backend checks:

```bash
cd backend
composer ci-check
```

Run frontend checks:

```bash
cd frontend
bun run lint
bun run build
```

All checks must pass before release.
