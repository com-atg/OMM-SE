# OMM Scholar Eval

A Laravel 13 app that receives scholar evaluation submissions from REDCap, computes per-category grade aggregates, writes them back to a destination REDCap project, delivers professional email notifications, and protects dashboard/process views with REDCap Advanced Link authorization.

---

## How It Works

```mermaid
sequenceDiagram
    participant RC as REDCap Source Project
    participant User as REDCap User
    participant App as OMM Scholar Eval
    participant Dest as REDCap OMMScholarEvalList
    participant Mail as Mail Server

    User->>RC: Click Advanced Link
    RC->>App: POST /omm_ace/redcap/launch (authkey)
    App->>RC: validate authkey + export user role assignments
    App->>User: dashboard/process access if role authorized
    RC->>App: POST /omm_ace/notify?token=<secret>
    App->>RC: exportRecords (single eval)
    App->>RC: exportRecords (all evals, scholar + semester)
    App->>App: Aggregate scores & comments
    App->>Dest: importRecords (nu/avg/comments fields)
    App->>Mail: EvaluationNotification (To: scholar, CC: faculty, BCC: admin)
```

---

## Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13 / PHP 8.4 |
| Runtime | PHP-FPM + Nginx (Alpine) |
| Process manager | Supervisor |
| Reverse proxy | Traefik (external) |
| Cache | File-based |
| Queue | Sync (inline) |
| Testing | Pest 4 |
| CI/CD | GitHub Actions |
| Containerisation | Docker (multi-stage) |
| Versioning | CalVer — `YYYY.HX.N` |

---

## Documentation

| Guide | Description |
|-------|-------------|
| [Architecture](docs/architecture.md) | System design, component breakdown, data flow |
| [REDCap Integration](docs/redcap-integration.md) | Source/destination schemas, webhook setup, Advanced Link setup, field mappings |
| [Local Development](docs/local-development.md) | Docker setup, environment variables, Mailhog |
| [Testing](docs/testing.md) | Pest test suite, running tests, test structure |
| [Production Deployment](docs/production.md) | CI/CD pipeline, CalVer tagging, Docker Hub, SSH deploy |
| [Security](docs/security.md) | Webhook auth, Advanced Link auth, HTTPS enforcement, input validation |

---

## Quick Start

```bash
# 1. Clone and install dependencies
git clone <repo-url> omm-se && cd omm-se
composer install
cp .env.example .env
php artisan key:generate

# 2. Configure REDCap tokens and mail in .env
#    REDCAP_URL, REDCAP_TOKEN, REDCAP_SOURCE_TOKEN, WEBHOOK_SECRET
#    AUTHORIZED_ROLES, REDCAP_TOKEN_PID_<pid> for Advanced Link access

# 3. Start the local stack
docker compose up -d

# 4. Run tests
php artisan test --compact
```

See [Local Development](docs/local-development.md) for the full setup guide.

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php  # Dashboard view
│   │   ├── NotifierController.php   # Webhook orchestrator
│   │   ├── ProcessController.php    # Bulk aggregation by PID
│   │   └── ScholarController.php    # Scholar detail view
│   └── Middleware/
│       ├── VerifyRedcapAdvancedLink.php # REDCap user/role auth
│       └── VerifyWebhookToken.php       # Shared-secret webhook auth
├── Mail/
│   └── EvaluationNotification.php  # Markdown mailable
└── Services/
    ├── RedcapAdvancedLinkService.php # Authkey + role authorization
    ├── RedcapSourceService.php      # Current-year source project API wrapper
    └── RedcapDestinationService.php # OMMScholarEvalList API wrapper

config/redcap.php                    # REDCap connection config
resources/views/emails/evaluation.blade.php

docker/
├── entrypoint.sh
├── nginx/default.conf
├── php/{php.ini,opcache.ini}
└── supervisor/supervisord.conf

tests/
├── Feature/NotifierControllerTest.php
└── Unit/{RedcapSourceServiceTest,EvaluationNotificationTest}.php
```
