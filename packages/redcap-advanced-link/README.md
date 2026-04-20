# REDCap Advanced Link Auth (Reusable Module)

A reference module for authenticating Laravel application requests against REDCap's **Advanced Link** feature. REDCap posts an `authkey` to your app, which is exchanged (server-to-server) for the user's identity and project role. If the role is on your allowlist, a session is established.

This folder is a **copy-paste template**, not a Composer package. Each target project pastes the module in, wires it up, and diverges as needed.

---

## What's in here

```
packages/redcap-advanced-link/
├── README.md                                     ← you are here
├── src/
│   ├── RedcapAdvancedLinkService.php             ← authkey exchange + role check
│   ├── Redcap_lib.php                            ← static REDCap REST API wrapper
│   └── Middleware/VerifyRedcapAdvancedLink.php   ← route guard
├── config/redcap-advanced-link.php               ← config block
└── tests/
    ├── Feature/VerifyRedcapAdvancedLinkTest.php
    └── Unit/RedcapAdvancedLinkServiceTest.php
```

---

## How the flow works

1. A REDCap project admin creates an Advanced Link pointing at `POST https://your-app.example/redcap/launch`.
2. When a user clicks the link inside REDCap, REDCap POSTs a short-lived `authkey` to that URL.
3. `VerifyRedcapAdvancedLink` middleware:
   - Calls `RedcapAdvancedLinkService::authorize($authKey, $authorizedRoles)`.
   - The service POSTs the authkey back to the REDCap API, gets `{ username, project_id, ... }`.
   - Pulls the user's `unique_role_name` from the project's user-role assignments.
   - If the role is in the allowlist, stores the payload in the session and lets the request through. Otherwise 403.
4. Subsequent requests reuse the session until it expires.

---

## Copy into a new project

### 1. Paste files

Copy into the target Laravel app:

| From (this repo)                                       | To (target app)                                        |
| ------------------------------------------------------ | ------------------------------------------------------ |
| `src/RedcapAdvancedLinkService.php`                    | `app/Services/RedcapAdvancedLinkService.php`           |
| `src/Redcap_lib.php`                                   | `app/Models/Redcap_lib.php`                            |
| `src/Middleware/VerifyRedcapAdvancedLink.php`          | `app/Http/Middleware/VerifyRedcapAdvancedLink.php`     |
| `config/redcap-advanced-link.php`                      | `config/redcap-advanced-link.php`                      |
| `tests/Feature/VerifyRedcapAdvancedLinkTest.php`       | `tests/Feature/VerifyRedcapAdvancedLinkTest.php`       |
| `tests/Unit/RedcapAdvancedLinkServiceTest.php`         | `tests/Unit/RedcapAdvancedLinkServiceTest.php`         |

Update namespaces to match your project (e.g. `Omm\RedcapAdvancedLink\*` → `App\Services\*` and `App\Http\Middleware\*`). Update imports in the tests accordingly.

If you'd rather keep the `Omm\RedcapAdvancedLink\*` namespace, add to `composer.json`:

```json
"autoload": {
    "psr-4": {
        "Omm\\RedcapAdvancedLink\\": "packages/redcap-advanced-link/src/"
    }
}
```

Then run `composer dump-autoload`.

### 2. Provide a REDCap API client

The service calls `$client::exportUserRoleAssignments(format, returnAs, url, token)` to pull project role assignments.

**`src/Redcap_lib.php` is bundled in this module** — a full static wrapper for the REDCap REST API (records, metadata, users, roles, DAGs, surveys, files, logging). If you pasted it into `app/Models/` per the table above, the default config picks it up automatically.

If your project already has its own REDCap client:

- Set `REDCAP_API_CLIENT=Your\\Own\\Client` in `.env`. Any class exposing a static `exportUserRoleAssignments(format, returnAs, url, token)` method works.
- Or override `RedcapAdvancedLinkService::fetchRoleAssignments()` in a subclass.

The bundled `Redcap_lib` also reads `REDCAP_URL` and `REDCAP_TOKEN` from env as defaults, so routine API calls elsewhere in the app (`Redcap_lib::exportRecords(...)`) work without extra plumbing.

### 3. Environment variables

```dotenv
REDCAP_URL=https://redcap.example.edu/api/
REDCAP_ADVANCED_LINK_ENABLED=true
AUTHORIZED_ROLES=mspe_coordinator,mspe_reviewer
REDCAP_TOKEN_PID_1846=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
REDCAP_TOKEN_PID_2031=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
# Optional — defaults to \App\Models\Redcap_lib::class
REDCAP_API_CLIENT=App\\Models\\Redcap_lib
```

`AUTHORIZED_ROLES` is comma-separated. `REDCAP_TOKEN_PID_{pid}` env vars are auto-discovered into `config('redcap-advanced-link.project_tokens')`.

### 4. Routes

```php
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\VerifyRedcapAdvancedLink;
use Illuminate\Support\Facades\Route;

Route::post('/redcap/launch', fn () => redirect()->route('dashboard'))
    ->middleware(VerifyRedcapAdvancedLink::class)
    ->name('redcap.launch');

Route::middleware(VerifyRedcapAdvancedLink::class)->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    // ...other protected routes
});
```

### 5. CSRF exemption

`/redcap/launch` receives a POST from REDCap, which has no CSRF token. Exempt it in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['/redcap/launch']);
})
```

### 6. REDCap side

In the source project, under **Project Setup → Additional customizations → Advanced Link**:

- **Link label:** e.g. "Open in MSPE Dashboard"
- **Link URL:** `https://your-app.example/redcap/launch`
- **Method:** POST
- **Pass the authkey:** enabled

Roles authorized at the REDCap project level must match `AUTHORIZED_ROLES` in the target app.

---

## Config reference

| Key                                   | Source                                  | Purpose                                                                 |
| ------------------------------------- | --------------------------------------- | ----------------------------------------------------------------------- |
| `redcap-advanced-link.url`            | `REDCAP_URL`                            | REDCap API endpoint                                                     |
| `redcap-advanced-link.enabled`        | `REDCAP_ADVANCED_LINK_ENABLED`          | Master switch. Auto-enabled if `AUTHORIZED_ROLES` is non-empty          |
| `redcap-advanced-link.authorized_roles` | `AUTHORIZED_ROLES` (comma-separated)  | Allowlist of `unique_role_name` values                                  |
| `redcap-advanced-link.session_key`    | hardcoded                               | Session key for persisted user payload                                  |
| `redcap-advanced-link.api_client`     | `REDCAP_API_CLIENT`                     | Class providing `exportUserRoleAssignments()`                           |
| `redcap-advanced-link.project_tokens` | `REDCAP_TOKEN_PID_{pid}`                | Per-PID API tokens, auto-populated                                      |

---

## Session payload

After a successful launch, `session('redcap.advanced_link.user')` contains:

```php
[
    'username'              => 'mmatalia',
    'project_id'            => '1846',
    'unique_role_name'      => 'mspe_coordinator',
    'data_access_group_name'=> 'OMM',          // may be ''
    'data_access_group_id'  => '1',            // may be ''
    'callback_url'          => 'https://redcap.example.edu/redcap_v14.0.0/index.php?pid=1846',
]
```

Also available on the request: `$request->attributes->get('redcap_advanced_link_user')`.

---

## Testing

The `tests/` folder uses Pest. After pasting:

```bash
php artisan test --compact --filter=AdvancedLink
```

Unit tests extend `RedcapAdvancedLinkService` with fakes so the REDCap API is never hit. The feature test uses `Http::fake()` and Mockery via Pest's `mock()` helper.

---

## Security notes

- `authkey` values are single-use and short-lived (REDCap enforces a ~60s TTL). Don't log them.
- Project tokens must stay server-side. They grant full API access to the project.
- Role lookups rely on REDCap's user-role assignments; if a user is removed from a role in REDCap, they lose access on the next authkey exchange (but the current session stays valid until expiry — shorten `SESSION_LIFETIME` if you need tighter revocation).
- Always serve `/redcap/launch` over HTTPS.

---

## Origin

Extracted from the OMM Scholar Evaluation app (project: `OMM_SE`, commit: staged prior to Okta SAML migration). Originals lived at:

- `app/Services/RedcapAdvancedLinkService.php`
- `app/Http/Middleware/VerifyRedcapAdvancedLink.php`
- `app/Models/Redcap_lib.php`
- `tests/Feature/RedcapAdvancedLinkMiddlewareTest.php`
- `tests/Unit/RedcapAdvancedLinkServiceTest.php`
- `config/redcap.php` (`advanced_link` block)
