# Production Deployment

## CI/CD Pipeline

Every push to `main` triggers a two-job GitHub Actions workflow.

```mermaid
flowchart TD
    PUSH[Push to main] --> T

    subgraph T[Job 1 — test]
        T1[Checkout code] --> T2[Setup PHP 8.4]
        T2 --> T3[Cache + install Composer deps]
        T3 --> T4[Copy .env.example → .env\nGenerate APP_KEY]
        T4 --> T5[php artisan test --compact]
    end

    T5 -->|✅ pass| R
    T5 -->|❌ fail| STOP[Deploy blocked]

    subgraph R[Job 2 — release]
        R1[Checkout full history] --> R2[Calculate CalVer tag\natomic retry loop]
        R2 --> R3[Setup Docker Buildx]
        R3 --> R4[Login to Docker Hub]
        R4 --> R5[Build & push image\ncache-from/to GHA]
        R5 --> R6[SSH deploy to server\npull + up --remove-orphans]
    end
```

### Required GitHub Secrets

| Secret | Description |
|--------|-------------|
| `DOCKERHUB_USERNAME` | Docker Hub account username |
| `DOCKERHUB_TOKEN` | Docker Hub access token (not your password) |
| `SSH_HOST` | Production server IP or hostname |
| `SSH_USER` | SSH user on the production server |
| `SSH_KEY` | Private SSH key (the server must have the public key in `authorized_keys`) |

---

## CalVer Versioning

Tags follow `YYYY.HX.N` where:
- `YYYY` — four-digit year
- `HX` — half-year (`H1` = Jan–Jun, `H2` = Jul–Dec)
- `N` — build number, incrementing from 1, **resets each half-year**

```
2026.H1.1   ← first build of H1 2026
2026.H1.2   ← second build
2026.H2.1   ← first build after July 1 (resets)
2027.H1.1   ← first build of 2027
```

```mermaid
flowchart LR
    A[Fetch all tags\nfor current prefix\ne.g. 2026.H1.*] --> B[Sort numerically\non last segment]
    B --> C{Any tags?}
    C -- No --> D[BUILD = 1]
    C -- Yes --> E[BUILD = LATEST + 1]
    D --> F[VERSION = PREFIX.BUILD]
    E --> F
    F --> G[git tag VERSION\ngit push origin VERSION]
    G -->|tag already exists\nanother run got there first| H[Retry up to 5×\nwith back-off]
    H --> A
```

The tag is pushed **before** the Docker image is built, making it the authoritative version identifier.

---

## Docker Image

### Multi-Stage Build

```mermaid
flowchart LR
    subgraph S1["Stage 1: node-builder (node:22-alpine)"]
        N1[npm ci] --> N2[npm run build\nproduces public/build]
    end
    subgraph S2["Stage 2: composer-builder (composer:2)"]
        C1[composer install\n--no-dev --optimize-autoloader] --> C2[composer dump-autoload]
    end
    subgraph S3["Stage 3: runtime (php:8.4-fpm-alpine)"]
        P1[Install system deps\nnginx, supervisor, extensions] --> P2[Copy php.ini, opcache.ini\nnginx.conf, supervisord.conf]
        P2 --> P3[COPY app from composer-builder]
        P3 --> P4[COPY public/build from node-builder]
        P4 --> P5[chown storage, bootstrap/cache]
    end
    S1 --> S3
    S2 --> S3
```

**PHP extensions included:** `mbstring`, `zip`, `gd`, `opcache`, `pcntl`, `bcmath`, `intl`

**OPcache tuning** (`docker/php/opcache.ini`):
- `validate_timestamps=0` — no filesystem stat on every request
- `revalidate_freq=0` — immutable cached bytecode
- `memory_consumption=128` MB
- `max_accelerated_files=10000`

### Image Tags

Each release pushes two tags to Docker Hub:

| Tag | Meaning |
|-----|---------|
| `{DOCKERHUB_USERNAME}/omm-se:2026.H1.3` | Specific build — immutable |
| `{DOCKERHUB_USERNAME}/omm-se:latest` | Always points to the most recent release |

---

## Server Setup

### Prerequisites

The production server needs:
- Docker Engine + Compose v2
- Traefik running and listening on ports `80` and `443` with `web` and `websecure` entrypoints
- SSH access configured for the deploy user

### Initial Setup

```bash
# On the production server
mkdir -p /opt/omm-se
cd /opt/omm-se

# Copy docker-compose.prod.yml
scp docker-compose.prod.yml user@server:/opt/omm-se/

# Create production .env from the example
cp .env.example .env
# Edit .env — set all required values (see below)
nano .env

# Pull and start for the first time
export DOCKERHUB_USERNAME=your-username
export APP_VERSION=latest
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

### Production `.env` Checklist

```bash
APP_ENV=production
APP_DEBUG=false
APP_KEY=                    # php artisan key:generate --show
APP_URL=https://your-domain.com

# Database — MySQL 8 (omm-ace-mysql service in docker-compose.prod.yml)
DB_CONNECTION=mysql
DB_HOST=omm-ace-mysql
DB_PORT=3306
DB_DATABASE=omm_se
DB_USERNAME=omm_se
DB_PASSWORD=                # generate a long random string
MYSQL_ROOT_PASSWORD=        # generate a long random string

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="OMM Scholar Eval"

REDCAP_URL=https://comresearchdata.nyit.edu/redcap/api/
REDCAP_TOKEN=               # Destination project token
WEBHOOK_SECRET=             # openssl rand -hex 32

# ── Okta SAML SSO ─────────────────────────────────────────────────────────────
# Get these from the Okta app's "Sign On" → "View SAML setup instructions".
SAML_IDP_ENTITY_ID=
SAML_IDP_SSO_URL=
SAML_IDP_SLO_URL=
SAML_IDP_X509_CERT=
SAML_SP_ENTITY_ID="${APP_URL}/saml/metadata"
SAML_SP_ACS_URL="${APP_URL}/saml/acs"
SAML_SP_SLO_URL="${APP_URL}/saml/logout"
SAML_ATTR_EMAIL=email
SAML_ATTR_NAME=displayName
SAML_STRICT=true
SAML_DEBUG=false
SAML_DEFAULT_REDIRECT=/

# ── Application role allowlists ──────────────────────────────────────────────
# Comma-separated emails, case-insensitive. Role is recomputed on every login.
SERVICE_USERS=
ADMIN_USERS=

DOCKERHUB_USERNAME=your-dockerhub-username
```

**Database:** On first boot the `omm-ace-mysql` container initializes a volume at `./data` (bind mount). The app's `entrypoint.sh` runs `php artisan migrate --force` against it. Sessions, cache, and queue all live in MySQL.

**Annual rotation:** For each new academic-year source project, add a project mapping in `/admin/settings` with the academic year, graduation year, REDCap PID, and source API token. The token is encrypted in MySQL.

**Enrolling Service / Admin users:** Add their emails to `SERVICE_USERS=` or `ADMIN_USERS=`. After editing `.env`, run `docker compose -f docker-compose.prod.yml up -d --force-recreate app` to pick up the new values. The role is re-evaluated on each SAML login. Faculty and Student accounts can also be managed from `/admin/users`; students auto-provision on first login as long as their email matches a record in the destination REDCap project.

**Okta application:** In the Okta admin console create a new SAML 2.0 app.
- Single sign-on URL / ACS: `https://your-domain.com/saml/acs`
- Audience URI (SP Entity ID): `https://your-domain.com/saml/metadata`
- Name ID format: `EmailAddress`
- Attribute statements: map `email` → `user.email`, `displayName` → `user.displayName`
- Copy the IdP Entity ID, SSO URL, SLO URL, and x509 certificate into the corresponding `SAML_IDP_*` env vars.

---

## Container Startup Sequence

```mermaid
sequenceDiagram
    participant D as Docker
    participant E as entrypoint.sh
    participant SV as Supervisord
    participant FPM as PHP-FPM
    participant NGX as Nginx

    D->>E: docker compose up
    E->>E: php artisan config:cache
    E->>E: php artisan route:cache
    E->>E: php artisan view:cache
    E->>SV: exec supervisord
    SV->>FPM: start php-fpm -F (priority 5)
    SV->>NGX: start nginx (priority 10)
    Note over FPM,NGX: Container healthy — ready for traffic
```

---

## Traefik Routing (Production)

The app registers itself with the external Traefik instance via Docker labels:

```mermaid
flowchart LR
    INET[Internet] -->|:80| TR[Traefik]
    INET -->|:443| TR
    TR -->|HTTP router\nomm-se-http| REDIR[301 redirect\nto HTTPS]
    TR -->|HTTPS router\nomm-se\nPathPrefix /omm_ace| STRIP[stripprefix\n/omm_ace]
    STRIP --> APP[app container :80]
```

- Path prefix `/omm_ace` is stripped before the request reaches Laravel, so routes are defined as `/notify`, `/saml/login`, `/saml/acs`, `/student`, `/faculty`, etc.
- TLS is terminated by Traefik using whatever certificate resolver it is already configured with.

---

## Rolling Deployment

The deploy step performs a zero-downtime update of the app container only:

```bash
# Pull the newly built image
docker compose -f docker-compose.prod.yml pull app

# Recreate the app container; Traefik reconnects automatically
docker compose -f docker-compose.prod.yml up -d --remove-orphans app
```

`entrypoint.sh` runs `php artisan migrate --force` on startup, so any new migrations are applied before the new container serves traffic. The MySQL container (`omm-ace-mysql`) is never recreated during an app rollout — only the `omm-ace-app` service is replaced. To roll back the app, set `APP_VERSION` to a previous CalVer tag and re-run the same commands. Destructive schema changes require a manual backup of the `./data` bind mount first.

```bash
export APP_VERSION=2026.H1.2
docker compose -f docker-compose.prod.yml pull app
docker compose -f docker-compose.prod.yml up -d --remove-orphans app
```
