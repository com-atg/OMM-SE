# Architecture

## System Context

The app sits between two REDCap projects and a mail server. It has no persistent database — all state lives in REDCap and the file system cache.

```mermaid
C4Context
    title OMM Scholar Eval — System Context

    Person(faculty, "Faculty", "Submits scholar evaluations in REDCap")
    Person(scholar, "Scholar", "Receives email notification with scores")
    Person(admin, "Administrator", "Uses REDCap Advanced Link to access dashboard/process views and is BCC'd on notifications")

    System(app, "OMM Scholar Eval", "Laravel 13 webhook processor")

    System_Ext(src, "REDCap Source Project", "OMMACEvaluations AY20XX-20XX\nRotates each academic year")
    System_Ext(dest, "REDCap OMMScholarEvalList", "Aggregated grade records\nper scholar per semester")
    System_Ext(mail, "SMTP Mail Server", "Delivers email notifications")
    System_Ext(traefik, "Traefik", "Reverse proxy — TLS termination,\nHTTP→HTTPS redirect, path routing")

    Rel(faculty, src, "Submits evaluation")
    Rel(admin, src, "Clicks Advanced Link")
    Rel(src, app, "Advanced Link launch + Data Entry Trigger", "HTTPS POST")
    Rel(app, src, "Exports eval records", "REDCap API")
    Rel(app, dest, "Imports aggregated grades", "REDCap API")
    Rel(app, mail, "Sends notification email", "SMTP")
    Rel(mail, scholar, "Delivers email")
    Rel(mail, admin, "BCC delivery")
    Rel(traefik, app, "Forwards /omm_ace/* requests", "HTTP")
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
    VerifyRedcapAdvancedLink --> RedcapAdvancedLinkService : validates
    VerifyWebhookToken --> NotifierController : guards
```

---

## Advanced Link Request Flow

```mermaid
sequenceDiagram
    participant TR as Traefik
    participant MW as VerifyRedcapAdvancedLink
    participant RAS as RedcapAdvancedLinkService
    participant RC as REDCap API
    participant UI as Dashboard/Process/Scholar Views

    TR->>MW: POST /omm_ace/redcap/launch authkey=...
    MW->>RAS: authorize(authkey, AUTHORIZED_ROLES)
    RAS->>RC: POST authkey + format=json
    RC-->>RAS: username + project_id
    RAS->>RC: exportUserRoleAssignments using REDCAP_TOKEN_PID_project
    alt role authorized
        MW->>MW: store REDCap user in session
        MW-->>TR: redirect /
    else unauthorized or invalid
        MW-->>TR: 403 Forbidden
    end

    TR->>MW: GET protected page
    MW->>MW: verify session role
    MW->>UI: proceed
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
| Sessions | `SESSION_DRIVER=cookie` — no server-side state |
| Cache | `CACHE_STORE=file` — scholar lookup cached 1 h in `storage/framework/cache` |
| Queue | `QUEUE_CONNECTION=sync` — webhook processed inline |
| Migrations | None — no schema to manage |
| Persistence | All data lives in REDCap |

Advanced Link authorization data is stored in the encrypted Laravel cookie session. No REDCap user records are written to local storage.
