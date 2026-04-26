# OMM Scholar Eval

A Laravel 13 app that receives scholar evaluation submissions from REDCap, computes per-category grade aggregates, writes them back to a destination REDCap project, delivers email notifications to scholars and faculty, and exposes a dashboard protected by Okta SAML SSO.

---

## How It Works

```mermaid
sequenceDiagram
    participant RC as REDCap Source Project
    participant App as OMM Scholar Eval
    participant Dest as REDCap OMMScholarEvalList
    participant Mail as Mail Server
    participant Scholar as Scholar (browser)

    RC->>App: POST /notify?token=<secret> (Data Entry Trigger)
    App->>RC: exportRecords (single eval)
    App->>RC: exportRecords (all evals, scholar + semester)
    App->>App: Aggregate scores & comments
    App->>Dest: importRecords (nu/avg/comments fields)
    App->>Mail: EvaluationNotification (To: scholar, CC: faculty)

    Scholar->>App: GET / (unauthenticated)
    App-->>Scholar: 302 → Okta login
    Scholar->>App: POST /saml/acs (Okta assertion)
    App-->>Scholar: dashboard / scholar detail
```

---

## Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13 / PHP 8.4 |
| Runtime | PHP-FPM + Nginx (Alpine) |
| Process manager | Supervisor |
| Reverse proxy | Traefik (external) |
| Database | MySQL 8 |
| Sessions / Cache / Queue | Database driver |
| Authentication | Okta SAML 2.0 via `onelogin/php-saml` |
| Authorization | App-level role enum (Service / Admin / Student) |
| Testing | Pest 4 — 94 tests |
| CI/CD | GitHub Actions |
| Containerisation | Docker (multi-stage) |
| Versioning | CalVer — `YYYY.HX.N` |

---

## Roles

| Role | Access |
|------|--------|
| **Service** | Everything — dashboard, all scholar records, faculty view, `/process/*` bulk aggregation, `/admin/users` user management, `/admin/settings` project-mapping management, impersonation |
| **Admin** | Dashboard, all scholar records (read-only), faculty view. No user/settings management. |
| **Faculty** | Faculty roster view scoped to evaluations they authored. |
| **Student** | Own scholar record only. Redirected from dashboard to their detail page. |

Service and Admin users are seeded via the user-management UI or imported from REDCap; legacy `.env` allowlists (`SERVICE_USERS=`, `ADMIN_USERS=`) remain supported as a bootstrap fallback. Students auto-provision on first SAML login if their email matches a record in the REDCap destination project. Unmatched users see a 404.

Roles are persisted on the `users` table via the `Role` enum (`Service`, `Admin`, `Faculty`, `Student`) and enforced through Gates: `manage-users`, `manage-settings`, `run-process`, `view-student-page`.

---

## Documentation

| Guide | Description |
|-------|-------------|
| [Architecture](Docs/architecture.md) | System design, component breakdown, SAML + webhook data flows |
| [REDCap Integration](Docs/redcap-integration.md) | Source/destination schemas, webhook setup, field mappings |
| [Admin Features](Docs/admin-features.md) | User management, CSV import, project-mapping settings, impersonation |
| [Local Development](Docs/local-development.md) | Docker setup, environment variables, simulating SSO login |
| [Testing](Docs/testing.md) | Pest test suite, auth helpers, test structure |
| [Production Deployment](Docs/production.md) | CI/CD pipeline, CalVer tagging, Docker Hub, SSH deploy, Okta setup |
| [Security](Docs/security.md) | SAML validation, role model, webhook auth, secrets management |

---

## Quick Start

```bash
# 1. Clone and install dependencies
git clone <repo-url> omm-se && cd omm-se
composer install && npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env: set DB_*, REDCAP_*, SAML_IDP_*, SERVICE_USERS

# 3. Start the local stack (app + MySQL + Mailhog)
docker compose up -d

# 4. Run migrations and seed the default Service account
php artisan migrate --seed

# 5. Run tests
php artisan test --compact
```

See [Local Development](Docs/local-development.md) for the full setup guide including how to bypass SAML for local development.

---

## Project Structure

```
app/
├── Enums/
│   ├── Role.php                         # Service / Admin / Faculty / Student
│   └── WeightCategory.php               # Final-score weighting categories
├── Http/
│   ├── Controllers/
│   │   ├── Admin/SettingsController.php # Project-mapping CRUD (Service only)
│   │   ├── Admin/UserController.php     # User management + REDCap import + CSV import dispatch + impersonation
│   │   ├── Auth/LocalLoginController.php# DEV-only SAML bypass (APP_ENV=local)
│   │   ├── Auth/SamlController.php      # SAML SSO (login / ACS / logout / metadata)
│   │   ├── DashboardController.php      # Cohort overview (Service + Admin)
│   │   ├── FacultyController.php        # Faculty-scoped roster view
│   │   ├── NotifierController.php       # REDCap webhook orchestrator
│   │   ├── ProcessController.php        # Bulk aggregation by PID (Service only)
│   │   └── StudentController.php        # Scholar roster + token-keyed detail (scoped by role)
│   └── Middleware/
│       ├── RequireSamlAuth.php          # SAML session guard
│       └── VerifyWebhookToken.php       # Shared-secret webhook auth
├── Livewire/
│   ├── Admin/CsvUserImport.php          # Drag-drop CSV → editable preview → bulk create
│   └── FacultyDetail.php                # Faculty-scoped evaluation detail
├── Models/
│   ├── User.php                         # Role enum + soft deletes + UUID public_token
│   ├── ProjectMapping.php               # Source/destination REDCap PID mapping
│   └── CategoryWeight.php               # Final-score formula weights
├── Providers/AppServiceProvider.php     # Gate definitions
└── Services/
    ├── SamlService.php                  # Role resolution + user provisioning
    ├── RedcapSourceService.php          # Current-year source project API
    └── RedcapDestinationService.php     # OMMScholarEvalList API

config/
├── redcap.php
└── saml.php

resources/views/
├── admin/
│   ├── users/                           # Index, create, edit, import-csv pages
│   └── settings/                        # Project-mapping index + edit
├── livewire/admin/csv-user-import.blade.php
└── components/app-shell.blade.php       # Layout wrapper

packages/redcap-advanced-link/          # Reusable REDCap Advanced Link template
                                        # (not wired into this app — copy-paste for other projects)
```
