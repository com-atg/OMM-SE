# Architecture

## System Context

The app sits between two REDCap projects, an Okta tenant, and a mail server. State is persisted in MySQL (users, sessions, cache, queue) and in REDCap (scholar records, aggregates).

```mermaid
C4Context
    title OMM Scholar Eval — System Context

    Person(faculty, "Faculty", "Submits scholar evaluations in REDCap")
    Person(scholar, "Scholar", "Logs in via Okta; views own eval records")
    Person(admin, "Administrator", "Logs in via Okta; views all scholar records")
    Person(service, "Service User", "Logs in via Okta; full access + user management")

    System(app, "OMM Scholar Eval", "Laravel 13 web app + webhook processor")
    System_Ext(okta, "Okta", "SAML 2.0 Identity Provider")
    System_Ext(src, "REDCap Source Project", "OMMACEvaluations AY20XX-20XX\nRotates each academic year")
    System_Ext(dest, "REDCap OMMScholarEvalList", "Aggregated grade records\nper scholar per semester")
    System_Ext(mail, "SMTP Mail Server", "Delivers email notifications")
    System_Ext(traefik, "Traefik", "Reverse proxy — TLS termination,\nHTTP→HTTPS redirect")

    Rel(faculty, src, "Submits evaluation")
    Rel(scholar, okta, "Authenticates via SSO")
    Rel(admin, okta, "Authenticates via SSO")
    Rel(service, okta, "Authenticates via SSO")
    Rel(okta, app, "SAML assertion", "HTTPS POST /saml/acs")
    Rel(src, app, "Data Entry Trigger", "HTTPS POST /notify")
    Rel(app, src, "Exports eval records", "REDCap API")
    Rel(app, dest, "Imports aggregated grades", "REDCap API")
    Rel(app, mail, "Sends notification email", "SMTP")
    Rel(mail, scholar, "Delivers email")
    Rel(traefik, app, "Forwards requests", "HTTP")
```

---

## Container Architecture

```mermaid
graph TD
    subgraph Docker Container
        direction TB
        SV[Supervisor]
        SV --> NGINX[Nginx :80]
        SV --> FPM[PHP-FPM :9000]
        NGINX -->|FastCGI| FPM
        FPM --> APP[Laravel App]
    end

    TR[Traefik\nexternal] -->|HTTP :80| NGINX
    APP -->|REDCap API\nREDCAP_SOURCE_TOKEN| RC1[(REDCap Source Project\nrotates each academic year)]
    APP -->|REDCap API\nREDCAP_TOKEN| RC2[(REDCap OMMScholarEvalList\npermanent destination)]
    APP -->|SMTP| SMTP[Mail Server]
    APP -->|Read/Write| FS[(File System\ncache / views / logs)]
```

**Supervisor** manages two processes inside one container:

| Process | Command | Priority |
|---------|---------|----------|
| php-fpm | `php-fpm -F` | 5 (starts first) |
| nginx | `nginx -g "daemon off;"` | 10 |

No queue worker is needed — `QUEUE_CONNECTION=sync` processes jobs inline during the request.

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
        +getRecord(recordId) array
        +getScholarEvals(datatelId, semester) array
        +SCORE_FIELDS: array
        +CATEGORY_LABELS: array
        +DEST_CATEGORY: array
    }

    class RedcapDestinationService {
        +findScholarByDatatelId(datatelId) array|null
        +getScholarRecord(recordId) array
        +updateScholarRecord(data) string
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
        +scholarRecord: array
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
    NotifierController --> EvaluationNotification : creates
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
        SC->>RC: findScholarByEmail(email)
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

    NC->>SRC: getRecord(recordId)
    SRC-->>NC: evalRecord[]

    NC->>NC: validate student, semester, eval_category

    NC->>SRC: getScholarEvals(scholarCode, semesterCode)
    SRC-->>NC: allEvals[]

    NC->>NC: aggregate(allEvals, semester)
    Note over NC: Sum scores per category<br/>Count evals, skip out-of-range<br/>Concatenate comments

    NC->>DST: findScholarByDatatelId(scholarCode)
    Note over DST: filterLogic [datatelid]='...' + 1h cache
    DST-->>NC: scholarRecord[]

    NC->>DST: updateScholarRecord(payload)
    DST-->>NC: "1"

    NC->>MAIL: EvaluationNotification
    Note over MAIL: To: scholar<br/>CC: faculty<br/>BCC: admin

    NC-->>TR: 200 OK
```

---

## Aggregation Logic

For each webhook trigger the app re-computes the full semester aggregate from scratch (not incremental), ensuring the destination is always consistent even if earlier records were corrected.

```mermaid
flowchart TD
    A[Fetch all evals\nfor scholar + semester] --> B{For each eval}
    B --> C{Score field\npresent & non-empty?}
    C -- No --> G
    C -- Yes --> D{0 ≤ score ≤ 100?}
    D -- No --> E[Log warning, skip]
    E --> G
    D -- Yes --> F[Add to sum, increment count]
    F --> G{More evals?}
    G -- Yes --> B
    G -- No --> H[Compute avg = sum / count per category]
    H --> I[Build REDCap payload\nsem_nu_cat, sem_avg_cat\nsem_nu_comments, sem_comments]
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

## Stateless Design

The application intentionally has no database. This simplifies operations significantly:

| Concern | Solution |
|---------|---------|
| Sessions | `SESSION_DRIVER=database` — `sessions` table in MySQL |
| Cache | `CACHE_STORE=database` — `cache` table; scholar roster cached 10 min, per-scholar 1 h |
| Queue | `QUEUE_CONNECTION=database` — `jobs` table; bulk-aggregation job dispatched after response |
| Migrations | `users`, `sessions`, `cache`, `jobs`, `password_reset_tokens` |
| Persistence | User records (with roles + REDCap record IDs) in MySQL; aggregated grades in REDCap |

User authentication state is stored in the database-backed session. The `users` table caches each scholar's `redcap_record_id` after their first SAML login, avoiding a REDCap API call on every request.
