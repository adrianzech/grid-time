# API access

Grid Time exposes a read-only schedule API. Direct requests to the following collections require an API key:

- `/api/series`
- `/api/seasons`
- `/api/events`
- `/api/sessions`

Send the key in every direct request:

```http
X-API-Key: gt_live_<identifier>_<secret>
```

API keys are server-side secrets. Do not use them in browser or mobile applications and never commit them to a repository.

## Third-party keys

Create a third-party key with the default limit of 120 requests per minute:

```bash
php bin/console api-key:create "Partner name"
```

Use `api-key:list` to view key identifiers and status. Revoke a key immediately when it is no longer necessary:

```bash
php bin/console api-key:revoke <identifier>
```

The complete key is shown only when it is created. Invalid or revoked keys return `401`; requests above the per-key limit return `429`.

Third-party browser integrations are intentionally unsupported because a key in browser code can always be extracted. Use a separate server-side integration instead.

## First-party Nuxt proxy

Browser schedule requests use Nuxt's server-side proxy at `/_schedule`. Create its first-party key at once:

```bash
php bin/console api-key:create "Frontend" --internal
```

Configure the Nuxt service with server-only environment variables:

```text
NUXT_INTERNAL_API_BASE=http://backend:8000
NUXT_FRONTEND_API_KEY=gt_live_<identifier>_<secret>
```

`NUXT_INTERNAL_API_BASE` is the internal Symfony service origin without `/api`. The proxy adds `/api`, forwards only the supported schedule collections and their allowlisted filters, and attaches `NUXT_FRONTEND_API_KEY` before calling Symfony. Do not use `localhost` inside the Nuxt container: it refers to the Nuxt container itself.

First-party keys bypass Symfony's per-key rate limit. This does not configure a rate limit for `/_schedule`; add one at the reverse proxy separately if it is required.

## Compose routing and secrets

The Compose deployment uses the same public `DOMAIN` for the application and the API. Traefik routes `/api` and `/bundles/apiplatform` to the `backend` service; all other routes, including `/_schedule`, reach the Nuxt frontend. The `backend` and `frontend` services share the internal `gridtime` network, so `NUXT_INTERNAL_API_BASE` does not need a public address.

Keep `API_KEY_PEPPER` and `NUXT_FRONTEND_API_KEY` in local or deployment environment files, not in labels, images, or browser-exposed variables. See the [README](../README.md#environment-configuration) for the local and Compose configuration files.
