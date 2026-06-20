# API access

The Symfony API is a read-only schedule API for series, seasons, events and sessions. Third-party integrations must send an API key with every request:

```http
X-API-Key: gt_live_<identifier>_<secret>
```

Keys are server-side secrets. Do not use them in browser applications, mobile apps or public repositories.

## Key management

Create a third-party key with the default 120 requests per minute:

```bash
php bin/console api-key:create "Partner name"
```

Use `api-key:list` to view identifiers and status, and revoke a key immediately with:

```bash
php bin/console api-key:revoke <identifier>
```

The full key is shown only once when it is created. Invalid or revoked keys return `401`; requests above the key limit return `429`.

## First-party frontend

Nuxt uses a server-side schedule proxy at `/_schedule`. Configure the following deployment secrets on the Nuxt service:

```text
NUXT_INTERNAL_API_BASE=http://frankenphp
NUXT_FRONTEND_API_KEY=gt_live_<identifier>_<secret>
```

Create this key once using `api-key:create "Nuxt frontend" --internal`. It bypasses the per-key backend limit because Traefik must rate-limit `/_schedule` by client IP instead (120 requests per minute is the default policy). The proxy only exposes the four schedule collections and their supported filters.

`NUXT_INTERNAL_API_BASE` is the internal Symfony service origin, without `/api` (for example `http://frankenphp`). The proxy adds `/api` itself. It also accepts a base URL that ends in `/api` for compatibility. Do not use `localhost` inside a Nuxt container: it refers to the Nuxt container, not FrankenPHP.

## Traefik routing

Route the public application host to Nuxt. Route the third-party API host to FrankenPHP. Nuxt and FrankenPHP must share an internal network so `NUXT_INTERNAL_API_BASE` never uses a public address. Keep `API_KEY_PEPPER` and `NUXT_FRONTEND_API_KEY` in the deployment secret store, not in labels, images or committed environment files.

Third-party browser integrations are intentionally unsupported: a browser API key can always be extracted. They must use their own server-side backend.
