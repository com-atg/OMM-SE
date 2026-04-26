# Architecture

## System Context

The app sits between annual REDCap source projects, a permanent REDCap destination project, an Okta tenant, and a mail server. State is persisted in MySQL (users, project mappings, sessions, cache, queue) and in REDCap (student records, aggregates).

```mermaid
C4Context
    title OMM Scholar Eval — System Context

    Person(faculty, "Faculty", "Submits student evaluations in REDCap; views own evaluation roster in app")
    Person(student, "Student", "Logs in via Okta; views own eval records")
    Person(admin, "Administrator", "Logs in via Okta; views all student records")
    Person(service, "Service User", "Logs in via Okta; full access + user management + project-mapping settings + impersonation")

    System(app, "OMM Scholar Eval", "Laravel 13 web app + webhook processor")
    System_Ext(okta, "Okta", "SAML 2.0 Identity Provider")
    System_Ext(src, "REDCap Source Project", "OMMACEvaluations AY20XX-20XX\nRotates each academic year")
    System_Ext(dest, "REDCap OMMScholarEvalList", "Aggregated grade records\nper student per semester")
    System_Ext(mail, "SMTP Mail Server", "Delivers email notifications")
    System_Ext(traefik, "Traefik", "Reverse proxy — TLS termination,\nHTTP→HTTPS redirect")

    Rel(faculty, src, "Submits evaluation")
    Rel(student, okta, "Authenticates via SSO")
    Rel(admin, okta, "Authenticates via SSO")
    Rel(service, okta, "Authenticates via SSO")
    Rel(okta, app, "SAML assertion", "HTTPS POST /saml/acs")
    Rel(src, app, "Data Entry Trigger", "HTTPS POST /notify")
    Rel(app, src, "Exports eval records", "REDCap API")
    Rel(app, dest, "Imports aggregated grades", "REDCap API")
    Rel(app, mail, "Sends notification email", "SMTP")
    Rel(mail, student, "Delivers email")
    Rel(traefik, app, "Forwards requests", "HTTP")
```

---

## Container Architecture

```mermaid
graph TD
    subgraph Docker Container
        direction TB
        SV[Supervisor]
        SV --> NGINX[Nginx :8080]
        SV --> FPM[PHP-FPM :9000]
        NGINX -->|FastCGI| FPM
        FPM --> APP[Laravel App]
    end

    TR[Traefik\nexternal] -->|HTTP :8080| NGINX
    APP -->|REDCap API\nproject_mappings.redcap_token| RC1[(REDCap Source Project\nrotates each academic year)]
    APP -->|REDCap API\nREDCAP_TOKEN| RC2[(REDCap OMMScholarEvalList\npermanent destination)]
    APP -->|SMTP| SMTP[Mail Server]
    APP -->|Read/Write| FS[(File System\ncache / views / logs)]
```

**Supervisor** manages two processes inside one container:

| Process | Command | Priority |
|---------|---------|----------|
| php-fpm | `php-fpm -F` | 5 (starts first) |
| nginx | `nginx -g "daemon off;"` | 10 |

Bulk processing is dispatched with `dispatchAfterResponse()` and stores progress in the cache. In development, `composer run dev` starts `queue:listen`; production should run a queue worker when `QUEUE_CONNECTION=database`.

---

## Component Breakdown

```mermaid
classDiagram
    class NotifierController {
        +__invoke(Request, RedcapSourceService, RedcapDestinationService) Response
        -aggregate(evals, semester) array
        -SEMESTER_MAP: array
    }

    class RedcapSourceService {
        +getRecord(recordId, token) array
        +getStudentEvals(datatelId, semester, token) array
        +fetchAllRecords(token) array
        +getCompletedEvaluationRecords(token) array
        +SCORE_FIELDS: array
        +CATEGORY_LABELS: array
        +DEST_CATEGORY: array
    }

    class RedcapDestinationService {
        +findStudentByDatatelId(datatelId) array|null
        +findStudentByEmail(email) array|null
        +getStudentRecord(recordId) array
        +updateStudentRecord(data) string
    }

    class RedcapAdvancedLinkService {
        +authorize(authKey, authorizedRoles) array|null
        +isRoleAuthorized(role, authorizedRoles) bool
    }

    class VerifyRedcapAdvancedLink {
        +handle(Request, Closure) Response
    }

    class VerifyWebhookToken {
        +handle(Request, Closure) Response
    }

    class EvaluationNotification {
        +evalRecord: array
        +studentRecord: array
        +semester: string
        +aggregates: array
        +evalCategory: string
        +CRITERIA: array
        +SCORE_SCALE: array
        +envelope() Envelope
        +content() Content
    }

    NotifierController --> RedcapSourceService : injects
    NotifierController --> RedcapDestinationService : injects
    RequireSamlAuth --> SamlService : delegates
    VerifyWebhookToken --> NotifierController : guards
```

---

## SAML Authentication Flow

```mermaid
sequenceDiagram
    participant U as Browser
    participant TR as Traefik
    participant MW as RequireSamlAuth
    participant SC as SamlController
    participant SS as SamlService
    participant IDP as Okta
    participant DB as MySQL (users)
    participant RC as REDCap OMMScholarEvalList

    U->>TR: GET /
    TR->>MW: forward
    MW-->>U: 302 → /saml/login (stores intended URL)

    U->>SC: GET /saml/login
    SC-->>U: 302 → Okta SSO URL (AuthnRequest)

    U->>IDP: Okta login page
    IDP-->>U: POST /saml/acs (SAMLResponse)

    U->>SC: POST /saml/acs
    SC->>SS: extractIdentity(auth)
    SS-->>SC: email, name, nameId
    SC->>SS: loginFromAssertion(email, name, nameId)
    SS->>SS: resolveRole(email) — checks SERVICE_USERS / ADMIN_USERS
    SS->>DB: updateOrCreate user row
    DB-->>SS: User

    alt role = Student
        SC->>RC: findStudentByEmail(email)
        alt no match
            SC-->>U: 404 records-not-found view
        else matched
            SC->>DB: save redcap_record_id
            SC-->>U: 302 → intended URL
        end
    else role = Admin or Service
        SC-->>U: 302 → intended URL
    end
```

---

## Webhook Request Flow

```mermaid
sequenceDiagram
    participant TR as Traefik
    participant MW as VerifyWebhookToken
    participant NC as NotifierController
    participant SRC as RedcapSourceService
    participant DST as RedcapDestinationService
    participant MAIL as Mail

    TR->>MW: POST /omm_ace/notify?token=xxx
    MW->>MW: hash_equals(secret, token)
    alt token invalid
        MW-->>TR: 403 Forbidden
    end

    MW->>NC: __invoke(request)
    NC->>NC: validate record ID present

    NC->>NC: resolve project mapping by project_id
    NC->>SRC: getRecord(recordId, token)
    SRC-->>NC: evalRecord[]

    NC->>NC: validate student, semester, eval_category

    NC->>SRC: getStudentEvals(studentCode, semesterCode, token)
    SRC-->>NC: allEvals[]

    NC->>NC: aggregate(allEvals, semester)
    Note over NC: Sum scores per category<br/>Count evals, skip out-of-range<br/>Concatenate comments

    NC->>DST: findStudentByDatatelId(studentCode)
    Note over DST: filterLogic [datatelid]='...' + 1h cache
    DST-->>NC: studentRecord[]

    NC->>DST: updateStudentRecord(payload)
    DST-->>NC: "1"

    NC->>MAIL: EvaluationNotification
    Note over MAIL: To: student<br/>CC: faculty<br/>BCC: admin

    NC-->>TR: 200 OK
```

---

## Aggregation Logic

For each webhook trigger the app re-computes the full semester aggregate from scratch (not incremental), ensuring the destination is always consistent even if earlier records were corrected.

```mermaid
flowchart TD
    A[Fetch all evals\nfor student + semester] --> B{For each eval}
    B --> C{Score field\npresent & non-empty?}
    C -- No --> G
    C -- Yes --> D{0 ≤ score ≤ 100?}
    D -- No --> E[Log warning, skip]
    E --> G
    D -- Yes --> F[Add to sum, increment count]
    F --> G{More evals?}
    G -- Yes --> B
    G -- No --> H[Compute avg = sum / count per category]
    H --> I[Build REDCap payload\nsem_nu_cat, sem_avg_cat,\nsem_dates_cat, sem_nu_comments, sem_comments]
    I --> J[importRecords to destination]
```

**Category → field mapping:**

| eval_category | Label | Score field | Destination avg field |
|---|---|---|---|
| A | Teaching | `teaching_score` | `{sem}_avg_teaching` |
| B | Clinic | `clinical_performance_score` | `{sem}_avg_clinic` |
| C | Research | `research_total_score` | `{sem}_avg_research` |
| D | Didactics | `didactic_total_score` | `{sem}_avg_didactics` |

**Semester mapping:** `'1'` → `spring`, `'2'` → `fall`

---

## Persistence Design

The application keeps operational state in MySQL and aggregate evaluation data in REDCap:

| Concern | Solution |
|---------|---------|
| Sessions | `SESSION_DRIVER=database` — `sessions` table in MySQL |
| Cache | `CACHE_STORE=database` — `cache` table; destination roster cached 10 min, per-student lookup 1 h, process status 60 min |
| Queue | `QUEUE_CONNECTION=database` — `jobs` table; bulk-aggregation job dispatched after response |
| Migrations | `users`, `project_mappings`, `category_weights`, `sessions`, `cache`, `jobs`, `password_reset_tokens` |
| Persistence | User records (with roles + REDCap record IDs), project mappings, and category weights in MySQL; aggregated grades in REDCap |

User authentication state is stored in the database-backed session. The `users` table caches each student's `redcap_record_id` after their first SAML login, avoiding a REDCap API call on every request.

---

## Admin Surface

In addition to the webhook and student/faculty views, the app exposes a Service-only admin surface:

```mermaid
flowchart LR
    SVC[Service user] --> UI[/admin/users/]
    SVC --> SET[/admin/settings/]
    UI --> CRUD[Create / edit / delete users]
    UI --> CSV[CSV import - Livewire]
    UI --> RC[Import full REDCap roster]
    UI --> IMP[Impersonate non-Service user]
    SET --> PM[Project-mapping CRUD]
    SET --> RUN[Trigger per-mapping processing]
```

See [Admin Features](admin-features.md) for routes, gates, validation rules, and the CSV import workflow.

**Gates** (defined in `AppServiceProvider`):

| Gate | Allowed roles |
|------|---------------|
| `manage-users` | Service |
| `manage-settings` | Service |
| `manage-settings-records` | Service (sub-gate for project-mapping CRUD vs. process-only) |
| `run-process` | Service |
| `view-student-page` | Service, Admin, Student (own record only) |
| `view-dashboard` | Service, Admin, Faculty |
| `view-all-students` | Service, Admin |
| `view-faculty-detail` | Service, Admin, Faculty |
