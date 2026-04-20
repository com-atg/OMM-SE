# Local Development

## Prerequisites

| Tool | Version | Purpose |
|------|---------|---------|
| PHP | 8.4 | Running Artisan commands and tests locally |
| Composer | 2.x | PHP dependency management |
| Docker + Compose | v2 | Full local stack |
| Node / npm | 22 | Asset compilation (via Docker, or locally) |

---

## Local Stack

```mermaid
graph TD
    DEV[Developer\nbrowser / curl] -->|HTTPS /omm_ace/*| TR[Traefik\nexternal]
    TR -->|strip prefix\nforward HTTP| APP[app container\nNginx + PHP-FPM]
    APP -->|TCP :3306| DB[mysql container\nMySQL 8]
    APP -->|SMTP :1025| MH[Mailhog container]
    MH -->|Web UI| MHUI[:8025]
    APP -->|REDCap API| RC[(REDCap\ncomresearchdata.nyit.edu)]
    APP -->|SAML| OKTA[(Okta dev tenant)]
```

The local compose file starts three services:

| Service | Image | Ports exposed |
|---------|-------|--------------|
| `app` | Built from `Dockerfile` (runtime stage) | None — routed via Traefik |
| `mysql` | `mysql:8.0` | None — reachable on Docker network only; data in named volume `mysql-data` |
| `mailhog` | `mailhog/mailhog:latest` | `1025` (SMTP), `8025` (Web UI) |

Traefik is **not** in the compose file — it's expected to be running externally on the host.

---

## First-Time Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy environment file
cp .env.example .env

# 3. Generate application key
php artisan key:generate

# 4. Configure .env — required values:
#    REDCAP_URL, REDCAP_TOKEN, REDCAP_SOURCE_TOKEN
#    DB_* (defaults in .env.example work with the mysql compose service)
#    SAML_IDP_* values if testing the real SAML flow (see "Simulating SSO")
#    SERVICE_USERS / ADMIN_USERS for role enrollment
#    Mail settings (or leave as Mailhog defaults)

# 5. Start the stack (brings up app + mysql + mailhog)
docker compose up -d --build

# 6. Run migrations (entrypoint already does this in the container, but handy locally)
docker compose exec app php artisan migrate --seed

# 7. Verify the app is reachable
curl -s https://your-local-domain/omm_ace/up
```

The seeder creates a default Service-role user: `Mihir.Matalia@nyit.edu`. You can add your own by editing `database/seeders/DatabaseSeeder.php` or by putting your email in `SERVICE_USERS=` and signing in via SAML.

---

## Environment Variables

All variables live in `.env` (copied from `.env.example`).

### Application

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `OMM Scholar Eval` | Application name |
| `APP_ENV` | `local` | Environment name |
| `APP_KEY` | _(generated)_ | Laravel encryption key |
| `APP_DEBUG` | `true` | Enable debug output |
| `APP_URL` | `http://localhost` | Base URL |

### Database / Session / Cache / Queue

| Variable | Value | Notes |
|----------|-------|-------|
| `DB_CONNECTION` | `mysql` | MySQL 8 via the `mysql` docker-compose service |
| `DB_HOST` | `mysql` | Docker service name |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `omm_se` / `omm_se` / `secret` | Defaults match the compose service |
| `SESSION_DRIVER` | `database` | Sessions table populated by migrations |
| `CACHE_STORE` | `database` | Scholar lookup cache in `cache` table |
| `QUEUE_CONNECTION` | `database` | Jobs table; process with `php artisan queue:work` if you want async |

### REDCap

| Variable | Description |
|----------|-------------|
| `REDCAP_URL` | REDCap API base URL (`https://comresearchdata.nyit.edu/redcap/api/`) |
| `REDCAP_TOKEN` | Destination project token (OMMScholarEvalList) |
| `REDCAP_SOURCE_TOKEN` | Source project token — update each academic year when a new evaluation project is created |
| `WEBHOOK_SECRET` | Shared secret for webhook token verification. Leave empty locally to skip the check. |
| `REDCAP_TOKEN_PID_<pid>` | Source project API token keyed by REDCap PID, e.g. `REDCAP_TOKEN_PID_1846`. |

### Okta SAML SSO

| Variable | Description |
|----------|-------------|
| `SAML_IDP_ENTITY_ID` / `SAML_IDP_SSO_URL` / `SAML_IDP_SLO_URL` / `SAML_IDP_X509_CERT` | From the Okta app's SAML setup instructions |
| `SAML_SP_ENTITY_ID` / `SAML_SP_ACS_URL` / `SAML_SP_SLO_URL` | Defaults derive from `APP_URL` |
| `SAML_ATTR_EMAIL` / `SAML_ATTR_NAME` | Attribute names the Okta app sends in assertions |
| `SAML_STRICT` | `true` in all environments that talk to a real IdP |
| `SAML_DEBUG` | `true` to log assertion contents during local debugging |
| `SERVICE_USERS` / `ADMIN_USERS` | Comma-separated emails. Your email goes here for full access. |

### Mail (Mailhog defaults)

| Variable | Default |
|----------|---------|
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `mailhog` (Docker service name) |
| `MAIL_PORT` | `1025` |
| `MAIL_FROM_ADDRESS` | `noreply@omm-se.local` |

---

## Simulating a Webhook

With the stack running, simulate a REDCap Data Entry Trigger:

```bash
# Without token auth (WEBHOOK_SECRET empty locally)
curl -X POST https://your-local-domain/omm_ace/notify \
  -d "record=1&project_id=<current-year-pid>&instrument=omm_ace_evaluations"

# With token auth
curl -X POST "https://your-local-domain/omm_ace/notify?token=your-secret" \
  -d "record=1&project_id=<current-year-pid>&instrument=omm_ace_evaluations"
```

Check the result:
- Response should be `200` with an empty body
- Check logs: `docker compose logs app`
- View sent email: open `http://localhost:8025` (Mailhog Web UI)

---

## Simulating Advanced Link Authorization

When `AUTHORIZED_ROLES` is empty, Advanced Link checks are disabled for local development. To test the production-style flow, configure:

```bash
AUTHORIZED_ROLES=U-ROLE1,U-ROLE2
REDCAP_TOKEN_PID_1846=source-project-token
```

Then configure REDCap Advanced Link to post to:

```
https://your-local-domain/omm_ace/redcap/launch
```

Manual `curl` testing is limited because the `authkey` must be generated by REDCap for an active user session. The application-side contract is:

```bash
curl -X POST https://your-local-domain/omm_ace/redcap/launch \
  -d "authkey=<redcap-generated-authkey>"
```

Expected behavior:
- Valid authkey + authorized REDCap role → redirect to `/omm_ace/`
- Missing, expired, or unauthorized authkey → `403`
- REDCap API outage during authkey validation → `503`

---

## Email Preview

A development-only route renders the email template without hitting REDCap:

```
GET https://your-local-domain/omm_ace/test/email
```

This renders a stubbed Teaching (Category A) evaluation for Catherine Chin. Refresh after editing [`resources/views/emails/evaluation.blade.php`](../resources/views/emails/evaluation.blade.php) to preview changes.

---

## Useful Commands

```bash
# Run all tests
php artisan test --compact

# Run a specific test
php artisan test --compact --filter="aggregates scores"

# Clear file cache (e.g. after changing scholar lookup)
php artisan cache:clear

# Tail application logs
docker compose logs -f app

# Rebuild the Docker image after code changes
docker compose up -d --build app

# Open an interactive shell inside the container
docker compose exec app sh
```

---

## Hot Reload (Source Code)

The local compose file mounts the entire project into the container:

```yaml
volumes:
  - .:/var/www/html          # source code
  - /var/www/html/vendor     # keep container's vendor intact
  - /var/www/html/node_modules
  - /var/www/html/public/build
```

PHP file changes are reflected immediately — no rebuild needed. If you change `package.json` or Blade files that affect compiled assets, run:

```bash
npm run build   # or: npm run dev (watch mode)
```
