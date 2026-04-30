# OMM ACE Eval

A Laravel 13 app that receives student evaluation submissions from REDCap, computes per-slot aggregates against the scholar's 4-semester window, writes them back to a permanent destination REDCap project, delivers email notifications to students and faculty, and exposes role-based dashboards protected by Okta SAML SSO.

---

## How It Works

```mermaid
sequenceDiagram
    participant RC as REDCap Source Project
    participant App as OMM ACE Eval
    participant Dest as REDCap OMMScholarEvalList
    participant Mail as Mail Server
    participant Student as Student (browser)

    RC->>App: POST /notify?token=<secret> (Data Entry Trigger)
    App->>App: Resolve source token from active project mapping
    App->>RC: exportRecords (single evaluation)
    App->>Dest: findStudentByDatatelId (cohort_start_term/year + batch + is_active)
    App->>App: SemesterSlot::compute → slot 1–4 (or skip)
    App->>RC: exportRecords (all evals for student + semester + year)
    App->>App: Aggregate scores & comments into sem{slot}_* fields
    App->>Dest: importRecords (sem{slot}_nu/avg/dates/comments)
    App->>Mail: EvaluationNotification (To: student, CC: faculty, BCC: admin)

    Student->>App: GET / (unauthenticated)
    App-->>Student: 302 → Okta login
    Student->>App: POST /saml/acs (Okta assertion)
    App-->>Student: dashboard, student detail, or faculty view by role
```

---

## Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13 / PHP 8.4 runtime |
| UI | Livewire 4, Flux 2, Tailwind CSS 4, Vite |
| Runtime | PHP-FPM + Nginx (Alpine, non-root, port 8080) |
| Process manager | Supervisor |
| Reverse proxy | Traefik (external) |
| Database | MySQL 8 |
| Sessions / Cache / Queue | Database driver |
| Authentication | Okta SAML 2.0 via `onelogin/php-saml` |
| Authorization | App-level role enum (Service / Admin / Faculty / Student) persisted on `users.role` |
| Documentation viewer | `com-atg/laravel-docs-viewer` rendering `/Docs/*.md` + `README.md` at `/admin/docs` |
| Testing | Pest 4 |
| CI/CD | GitHub Actions |
| Containerisation | Docker (multi-stage) |
| Versioning | CalVer — `YYYY.HX.N` |

---

## Roles

| Role | Access |
|------|--------|
| **Service** | Everything: dashboard, all student records, faculty view, current or per-PID bulk aggregation, `/admin/users`, `/admin/settings` (with full CRUD on project mappings), CSV import, REDCap roster import, impersonation, email-template editor, `/admin/docs` documentation viewer |
| **Admin** | Dashboard, all student records, faculty view, **read-only access to `/admin/settings`**, and the email-template editor. No `/admin/users` access, no project-mapping CRUD, no bulk processing. |
| **Faculty** | Dashboard and faculty view scoped to evaluations they authored, matched by faculty email or faculty name. |
| **Student** | Own student record only. Redirected from the dashboard to `/student`. |

Roles are **stored** on `users.role` (the `Role` enum) and are no longer recomputed from env allowlists at every login. The `SERVICE_USERS=` and `ADMIN_USERS=` env variables seed initial Service/Admin accounts via `DatabaseSeeder` on first migration; subsequent role changes are made in the UI at `/admin/users`. Faculty and Student users can be created manually, imported via CSV, or imported in bulk from the destination REDCap roster. Students auto-provision on first SAML login if their email matches a record in the destination project. Unmatched users see a 404.

Authorization is enforced through Gates: `view-dashboard`, `view-all-students`, `view-faculty-detail`, `view-student-page`, `run-process`, `manage-users`, `manage-settings` (Service + Admin), `manage-settings-records` (Service only — sub-gate for project-mapping CRUD), `edit-email-template` (Service + Admin), and `view-docs` (Service only).

---

## Documentation

These same files are also browsable in-app at **`/admin/docs`** (Service-only, served by `com-atg/laravel-docs-viewer`).

| Guide | Description |
|-------|-------------|
| [Architecture](Docs/architecture.md) | System design, component breakdown, SAML + webhook data flows, slot 1–4 aggregation model |
| [REDCap Integration](Docs/redcap-integration.md) | Source/destination schemas, webhook setup, cohort/slot field mappings |
| [Admin Features](Docs/admin-features.md) | User management, CSV import, project-mapping settings, academic-year wizard, email-template editor, docs viewer, impersonation |
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
# Edit .env: set DB_*, REDCAP_URL, REDCAP_TOKEN, SAML_IDP_*, SERVICE_USERS
# Source project tokens are managed in /admin/settings project mappings.

# 3. Start the local stack (app + MySQL + Mailhog)
docker compose up -d

# 4. Run migrations and seed the default Service account
php artisan migrate --seed

# 5. Run tests
php artisan test --compact
```

See [Local Development](Docs/local-development.md) for the full setup guide, including the local login bypass and email preview route.

---

## Project Structure

```
app/
├── Enums/
│   └── Role.php                         # Service / Admin / Faculty / Student
├── Http/
│   ├── Controllers/
│   │   ├── Admin/SettingsController.php # Project-mapping CRUD + email-template preview
│   │   ├── Admin/UserController.php     # User management + REDCap import + CSV import dispatch + impersonation
│   │   ├── Auth/LocalLoginController.php# DEV-only SAML bypass (APP_ENV=local)
│   │   ├── Auth/SamlController.php      # SAML SSO (login / ACS / logout / metadata)
│   │   ├── DashboardController.php      # Cohort overview (Service/Admin/Faculty; students redirect)
│   │   ├── FacultyController.php        # Faculty-scoped roster view
│   │   ├── NotifierController.php       # REDCap webhook orchestrator (slot-aware)
│   │   ├── ProcessController.php        # Bulk aggregation by PID (Service only)
│   │   └── StudentController.php        # Student roster + token-keyed detail (scoped by role)
│   └── Middleware/
│       ├── RequireSamlAuth.php          # SAML session guard
│       └── VerifyWebhookToken.php       # Shared-secret webhook auth
├── Livewire/
│   ├── Admin/CsvUserImport.php          # Drag-drop CSV → editable preview → bulk create
│   ├── Dashboard.php                    # Dashboard stats and academic-year filter
│   └── FacultyDetail.php                # Faculty-scoped evaluation detail
├── Models/
│   ├── AppSetting.php                   # Key/value app config (e.g. custom email template); cached per key
│   ├── ProjectMapping.php               # Source REDCap PID + encrypted token; one row marked is_active
│   └── User.php                         # Role enum + soft deletes + UUID public_token + cohort fields
├── Providers/AppServiceProvider.php     # Gate definitions
├── Services/
│   ├── MailTemplateRenderer.php         # Renders the email_template AppSetting against sample data
│   ├── SamlService.php                  # Identity extraction + user provisioning
│   ├── RedcapSourceService.php          # Per-project source REDCap API (year-aware getStudentEvals)
│   └── RedcapDestinationService.php     # OMMScholarEvalList API (cohort-aware lookups)
├── Jobs/
│   ├── ImportScholarsJob.php            # Queued student import dispatched from the academic-year wizard
│   └── ProcessSourceProjectJob.php      # Queued bulk aggregation with cache-backed status
├── Support/
│   ├── EvalAggregator.php               # Builds sem{n}_nu/avg/dates/comments fields per category
│   └── SemesterSlot.php                 # Maps (semester code, date_lab, cohort start) → slot 1–4
└── Mail/
    └── EvaluationNotification.php       # Markdown email; consumes AppSetting('email_template') override

config/
├── docs-viewer.php                      # Service-only `/admin/docs` viewer config
├── redcap.php
└── saml.php

resources/
├── css/
│   ├── app.css
│   └── docs-prose.css                   # Markdown styles for the docs viewer
└── views/
    ├── admin/
    │   ├── users/                       # Index, create, edit, import-csv pages
    │   └── settings/                    # index + edit + new-source-project + import-students-result
    ├── components/
    │   └── admin/
    │       └── ⚡academic-year-wizard.blade.php  # 2-step wizard (mapping → import)
    ├── livewire/admin/csv-user-import.blade.php
    └── vendor/docs-viewer/              # Published views for `com-atg/laravel-docs-viewer`

packages/redcap-advanced-link/          # Reusable REDCap Advanced Link template
                                        # (not wired into this app — copy-paste for other projects)
```
