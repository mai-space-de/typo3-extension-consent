# Features — EXT:mai_consent

`mai_consent` is the GDPR-compliant cookie/consent management extension for the
`www.bgm-pulheim.org` TYPO3 project. It provides a configurable consent banner,
category-based content gating, a server-side consent log, and a backend statistics
module. It does **not** manage email marketing consent — that is owned by
`mai_newsletter` (see section 9).

---

## 1 · Consent Categories

Consent categories are the unit of consent: a user accepts or rejects a category,
and all third-party content guarded by that category is then revealed or kept hidden.

### Record schema (`tx_maiconsent_domain_model_consentcategory`)

| Field | Type | Required | Notes |
|---|---|---|---|
| `title` | `varchar(255)` | ✅ | Editor-facing label, e.g. "Analytics" |
| `identifier` | `varchar(255)` (slug) | ✅ | Machine key used in JS and ViewHelper, e.g. `analytics`; auto-generated from title; `uniqueInSite` eval |
| `description` | `text` | — | Shown in the banner modal |
| `is_required` | `tinyint` (bool) | — | When `1`, the category is pre-accepted and the user cannot deselect it |
| `sorting` | `int` | — | Manual sort order for banner display |

### `isRequired` semantics

Required categories (e.g. "Essential") are rendered with their checkbox
pre-checked and disabled in the consent modal. They are always set to `true`
in the JavaScript `acceptAll` / `rejectAll` actions and may never be rejected.
The server never logs a `false` entry for a required category.

### Repository query API

| Method | Return | Notes |
|---|---|---|
| `findAllOrdered()` | `array<ConsentCategory>` | All non-hidden categories ordered by `sorting ASC` |
| `findOneByIdentifier(string $identifier)` | `?ConsentCategory` | Used by `ConsentApiMiddleware` to resolve the incoming identifier to a UID |

---

## 2 · Consent Log

Every call to `POST /api/consent` writes a row to `tx_maiconsent_domain_model_consentlog`
for each category entry in the request. The table is **write-once** — rows are never
updated in place. Editors can view log entries in read-only mode from the TYPO3 List
module. The only routine that removes rows is the retention purge (see below), which
erases entries once they pass the configured age.

### Retention purge — `consent:purge-expired-logs`

To satisfy GDPR data-minimisation, entries older than a retention window are physically
erased (the stored — albeit anonymised — IP address is personal data, so expired rows
are hard-deleted rather than soft-deleted). `ConsentLogRetentionService::purgeExpired()`
computes the cutoff (`now − retentionDays`, clamped to ≥ 1 day) and delegates to
`ConsentLogRepository::deleteOlderThan()`.

The schedulable CLI command drives it:

```bash
# default retention (365 days)
vendor/bin/typo3 consent:purge-expired-logs

# custom retention window
vendor/bin/typo3 consent:purge-expired-logs --days=180
```

Register it as a recurring **Execute console command** task in the TYPO3 scheduler
(`schedulable: true`). The default window is `ConsentLogRetentionService::DEFAULT_RETENTION_DAYS`
(365); a non-positive `--days` value is rejected with exit code `Command::INVALID`.

### `ConsentLogRepository::addEntry()` method

```php
public function addEntry(
    int    $categoryUid,
    bool   $accepted,
    string $session,
    string $ipAddress,
    int    $storagePid,
): void
```

Inserts a row directly via `ConnectionPool` (bypasses Extbase persistence to avoid
overhead for high-frequency writes).

### `ConsentLogRepository::countPerCategory()` query

Returns `array<int, array{total: int, accepted: int}>` keyed by category UID:

```sql
SELECT category, accepted, COUNT(*) AS cnt
FROM tx_maiconsent_domain_model_consentlog
WHERE deleted = 0
GROUP BY category, accepted
```

Used by `ConsentStatisticsBackendController` to derive acceptance rates per category.

### IP anonymization

`ConsentApiMiddleware::anonymizeIp()` applies last-segment zeroing before storing:

| Input | Stored |
|---|---|
| `192.168.1.42` | `192.168.1.0` |
| `10.0.0.1` | `10.0.0.0` |
| `2001:db8:85a3::1` | `2001:db8:85a3::0` |
| `::1` | `::0` |
| `''` (empty) | `''` |

IPv4: last octet replaced with `.0` via `preg_replace('/\.\d+$/', '.0', $ip)`.
IPv6: last colon-delimited group replaced with `:0` via `preg_replace('/:[^:]*$/', ':0', $ip)`.

---

## 3 · Content Gate ViewHelper

`ContentGateViewHelper` wraps third-party content in a container that is hidden until
the user consents to the specified category.

### Arguments

| Argument | Type | Required | Default | Notes |
|---|---|---|---|---|
| `category` | `string` | ✅ | — | Category identifier matching `ConsentCategory::$identifier` |
| `placeholder` | `string` | — | `''` | Text shown until consent is granted; `htmlspecialchars`-encoded |

### Fluid namespace declaration

```html
{namespace mai=Maispace\MaiConsent\ViewHelper}

<mai:contentGate category="marketing" placeholder="Accept Marketing to view this content.">
    <iframe src="https://..."></iframe>
</mai:contentGate>
```

### Rendered HTML structure

```html
<div class="mai-consent-gate"
     data-consent-category="marketing"
     data-consent-state="pending">
    <div class="mai-consent-gate__content" hidden>
        <!-- children rendered server-side, hidden until consent -->
    </div>
    <div class="mai-consent-gate__placeholder">
        Accept Marketing to view this content.
    </div>
</div>
```

### JavaScript reveal

On `DOMContentLoaded`, `window.MaiConsent` reads the `mai_consent` cookie. If the
cookie contains `{categories: {marketing: true, ...}}`, the JS calls `updateGates()`,
which sets `data-consent-state="granted"`, removes `hidden` from
`.mai-consent-gate__content`, and adds `hidden` to `.mai-consent-gate__placeholder`.
Gates are re-evaluated whenever the user updates their consent preferences via the
banner.

### CSS class contract

| Class | Applied to | Purpose |
|---|---|---|
| `.mai-consent-gate` | Outer wrapper | Carries `data-consent-category` and `data-consent-state` |
| `.mai-consent-gate__content` | Content div | `hidden` attribute toggled by JS |
| `.mai-consent-gate__placeholder` | Placeholder div | `hidden` attribute toggled by JS |

`data-consent-state` values: `pending` (initial), `granted`, `denied`.

---

## 4 · Consent API Endpoint

### Middleware registration

Registered in `Configuration/RequestMiddlewares.php` as a PSR-15 middleware in
the `frontend` stack, before `typo3/cms-frontend/page-resolver` (path prefix
`/api/consent`).

### Request format

```
POST /api/consent
Content-Type: application/json

{
    "consents": [
        {"identifier": "analytics", "accepted": true},
        {"identifier": "marketing", "accepted": false}
    ],
    "storagePid": 42
}
```

| Field | Type | Notes |
|---|---|---|
| `consents` | `array` | Required; each entry must have `identifier` (string) and `accepted` (bool) |
| `storagePid` | `int` | PID of the consent-records storage page; falls back to `0` |

### Session resolution

`$session` is resolved in priority order:
1. `$request->getAttribute('frontend.user')->id` (FE user session ID when logged in)
2. `session_id()` (PHP session ID as fallback)
3. `''` (empty string if neither is available)

### Response

```json
{"success": true}
```

HTTP 405 if the method is not `POST`, 415 if `Content-Type` is not
`application/json`, 422 if `consents` is missing or not an array. Unrecognised
category identifiers are silently skipped (no error).

### Called by the JavaScript layer

`persistToServer()` in `consent.js` uses `navigator.sendBeacon` with a JSON blob.
`sendBeacon` is fire-and-forget — the browser does not wait for the response.

---

## 5 · Frontend Banner Plugin

### Plugin registration

```
CType:  maispace_maiconsent_banner
Plugin: MaiConsent → Banner
Action: BannerController::indexAction (fully cached)
```

Registered via `ext_localconf.php` as `ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT`.

### Controller behaviour

`BannerController::indexAction()` calls `categoryRepository->findAllOrdered()` and
assigns the result to the view as `categories`. The actual show/hide logic is
entirely client-side — the controller renders the complete banner/modal HTML on every
page load; `consent.js` hides or reveals it based on the cookie state.

### Fluid template path

```
EXT:mai_consent/Resources/Private/Templates/Frontend/Banner/Index.html
```

The template renders the banner bar and the full-screen modal with per-category
toggles. The `id="mai-consent-banner"` attribute on the banner element is required
by `consent.js` to read `data-storage-pid` and to show/hide the element.

---

## 6 · JavaScript API (`window.MaiConsent`)

`consent.js` exposes a minimal public API on `window.MaiConsent`:

| Method | Return | Description |
|---|---|---|
| `hasConsent(identifier)` | `boolean` | `true` if the named category is accepted in the current cookie |
| `getAll()` | `object\|null` | Full cookie payload `{categories: {...}, ts: timestamp}` or `null` if no cookie |

### Cookie structure

```json
{
    "categories": {
        "essential":  true,
        "analytics":  false,
        "marketing":  true
    },
    "ts": 1748736000000
}
```

Cookie name: `mai_consent` · Lifetime: 365 days · `SameSite=Lax; path=/`.

### Data-action API

Buttons inside the banner template control the UI via `data-consent-action`:

| Value | Effect |
|---|---|
| `acceptAll` | Sets all categories to `true`, writes cookie, calls API, hides banner |
| `rejectAll` | Sets all categories to `false`, writes cookie, calls API, hides banner |
| `openModal` | Shows `#mai-consent-modal` |
| `closeModal` | Hides `#mai-consent-modal` |

Submitting `#mai-consent-form` saves the current checkbox state (via
`[data-consent-identifier]` elements) and behaves identically to `acceptAll`.

### CustomEvent: `maiConsent:updated`

After any consent action (accept all, reject all, form save), `consent.js` dispatches a
`CustomEvent` on `window` so third-party scripts can react without polling:

```js
window.addEventListener('maiConsent:updated', function (e) {
  // e.detail = { essential: true, analytics: false, marketing: false, ... }
  if (e.detail.mapping) {
    initLeafletMap();
  }
});
```

| Property | Type | Description |
|---|---|---|
| `e.detail` | `object` | Category identifier → boolean map of current consent state |

---

## 7 · Backend Statistics Module

Registered at `maispace_mai_consent` under the `web` module group.
Access level: `user` (no admin-only restriction).

### Controller: `ConsentStatisticsBackendController::indexAction`

Loads all consent categories via `findAllOrdered()`, then joins the per-category
counts from `countPerCategory()` to produce a `$stats` array:

```php
$stats[] = [
    'category' => $category,          // ConsentCategory object
    'total'    => $counts['total'],   // total log entries for this category
    'accepted' => $counts['accepted'], // entries with accepted = 1
    'rate'     => 0.0–100.0,          // accepted / total * 100, 1 decimal place
];
```

The Fluid template at
`EXT:mai_consent/Resources/Private/Templates/Backend/ConsentStatisticsBackend/Index.html`
renders a table row per category. A `rate` of `0` is shown when no log entries exist.

---

## 8 · TypoScript Configuration

### Constants (`Configuration/TypoScript/constants.typoscript`)

```typoscript
plugin.tx_maiconsent {
    settings {
        # cat=plugin.tx_maiconsent/basic/10; type=string
        cookieName = mai_consent

        # cat=plugin.tx_maiconsent/basic/20; type=int+
        cookieLifetime = 365

        # cat=plugin.tx_maiconsent/basic/30; type=int+; label=UID of the consent detail page
        consentPageId = 0

        # cat=plugin.tx_maiconsent/basic/40; type=int+; label=Storage PID for consent log records
        storagePageId = 0
    }
}
```

| Setting | Default | Purpose |
|---|---|---|
| `cookieName` | `mai_consent` | Name of the first-party HTTP cookie; must match `const COOKIE_NAME` in `consent.js` |
| `cookieLifetime` | `365` | Cookie lifetime in days |
| `consentPageId` | `0` | PID of the page hosting the "Manage cookies" preferences view (used for links) |
| `storagePageId` | `0` | PID where consent log records are stored; passed as `data-storage-pid` on the banner element |

### Setup (`Configuration/TypoScript/setup.typoscript`)

```typoscript
plugin.tx_maiconsent {
    view {
        templateRootPaths.0 = EXT:mai_consent/Resources/Private/Templates/Frontend/
        partialRootPaths.0  = EXT:mai_consent/Resources/Private/Partials/
        layoutRootPaths.0   = EXT:mai_consent/Resources/Private/Layouts/
    }
    persistence {
        storagePid = {$plugin.tx_maiconsent.settings.storagePageId}
    }
    settings < plugin.tx_maiconsent.settings
}
```

Override view paths from your site package to customise banner/modal appearance:

```typoscript
plugin.tx_maiconsent.view.templateRootPaths.10 = EXT:my_site/Resources/Private/Templates/Consent/
```

---

## 9 · Integration Contract: Transactional vs Marketing Mail

`mai_consent` and `mai_mail` serve different concerns and operate **independently**.
Understanding the boundary prevents mis-wiring that either over-restricts transactional
mail or under-restricts marketing mail.

### Two independent consent domains

| Domain | Owner | Gate | Subject |
|---|---|---|---|
| **Frontend content consent** | `mai_consent` | Cookie `mai_consent` | Third-party embeds, tracking pixels, behavioral analytics on web pages |
| **Email marketing consent** | `mai_newsletter` | Subscriber status (`isSubscribed()`) | Newsletter campaigns and bulk promotional emails |

These domains are **fully independent**: a user may accept the `marketing` consent
category in `mai_consent` (allowing tracking pixels on the website) while never
subscribing to the newsletter — and vice versa.

### Rule: `mai_mail` is consent-agnostic

`mai_mail` is a transport layer. `MailService::queue()` places emails on a queue
and dispatches them asynchronously. It has no knowledge of consent categories and
must never be modified to check them. The consent gate lives in the calling extension,
not in the transport layer.

### Transactional email — no consent gate

Transactional emails are triggered by a **direct user action** and serve the
completion of a service the user initiated:

| Extension | Email | User action that triggers it |
|---|---|---|
| `mai_account` | Account confirmation | Registration form submission |
| `mai_member` | Application receipt | Membership application form |
| `mai_member` | Approval / rejection notification | Editor approval action |
| `mai_newsletter` | Double opt-in confirmation | Newsletter subscription form |
| `mai_events` | Registration confirmation | Event registration form |

These emails **must be sent regardless of `mai_consent` category state**. Never
check a `mai_consent` category before calling `MailService::queue()` for
transactional mail.

```php
// ✅ Correct — transactional, no consent check needed
$this->mailService->queue(
    recipient:  $application->getEmail(),
    subject:    'Your membership application was received',
    htmlBody:   $renderedHtml,
);
```

### Marketing/bulk email — gate on `mai_newsletter` subscriber status

Marketing emails (newsletters, campaign emails) must only be sent to users whose
`mai_newsletter` subscriber record has `status = 'subscribed'`. The gate is
`Subscriber::isSubscribed()` — not a `mai_consent` category.

```php
// ✅ Correct — marketing, gate on mai_newsletter subscriber status
$subscriber = $this->subscriberRepository->findOneByEmail($email);
if ($subscriber === null || !$subscriber->isSubscribed()) {
    return; // not subscribed — do not send
}
$this->mailService->queue(
    recipient: $subscriber->getEmail(),
    subject:   $campaign->getSubject(),
    htmlBody:  $renderedHtml,
);
```

### Decision table

| Email type | Check `mai_consent` category? | Check `mai_newsletter` subscriber? |
|---|---|---|
| Transactional (confirmation, notification, reminder) | ❌ Never | ❌ Not applicable |
| Newsletter / campaign | ❌ Never | ✅ Required (`isSubscribed()`) |
| Password reset / account recovery | ❌ Never | ❌ Not applicable |

### Anti-patterns to avoid

```php
// ❌ Wrong — mai_consent is for frontend content, not email
if ($this->consentCategoryRepository->findOneByIdentifier('marketing')) {
    $this->mailService->queue(...);
}

// ❌ Wrong — newsletter subscriber check on transactional mail
$sub = $this->subscriberRepository->findOneByEmail($email);
if ($sub && $sub->isSubscribed()) {
    $this->mailService->queue(/* confirmation email */...);
}
```

### Summary

```
Frontend page visit
  └── User has marketing consent?          ← checked by mai_consent (cookie)
        Yes → show tracking pixel, embed
        No  → hide gated content

Newsletter dispatch
  └── User is subscribed?                  ← checked by mai_newsletter (DB)
        Yes → queue via mai_mail
        No  → skip

Transactional email
  └── (no consent gate)                    ← always queue via mai_mail
```

---

## 10 · Database Tables

### `tx_maiconsent_domain_model_consentcategory`

| Column | Type | Description |
|---|---|---|
| `uid` | `int unsigned` | Auto-increment primary key |
| `pid` | `int` | Storage page UID |
| `tstamp` | `int` | Unix timestamp of last update |
| `crdate` | `int` | Unix timestamp of creation |
| `cruser_id` | `int` | UID of the creating backend user |
| `deleted` | `tinyint` | Soft-delete flag |
| `hidden` | `tinyint` | Visibility flag |
| `starttime` | `int unsigned` | Publish start (TYPO3 enableColumn) |
| `endtime` | `int unsigned` | Publish end (TYPO3 enableColumn) |
| `sorting` | `int` | Manual sort order |
| `sys_language_uid` | `int` | Language overlay UID |
| `l10n_parent` | `int` | Parent UID for language overlays |
| `l10n_diffsource` | `mediumblob` | Diff source for translation module |
| `title` | `varchar(255)` | Category label |
| `identifier` | `varchar(255)` | Slug key used by JS and ContentGateViewHelper |
| `description` | `text` | Description shown in consent modal |
| `is_required` | `tinyint unsigned` | `1` = pre-accepted and non-deselectable |

### `tx_maiconsent_domain_model_consentlog`

| Column | Type | Description |
|---|---|---|
| `uid` | `int unsigned` | Auto-increment primary key |
| `pid` | `int` | Storage page UID |
| `tstamp` | `int` | Unix timestamp of log write |
| `crdate` | `int` | Unix timestamp of log write |
| `deleted` | `tinyint` | Soft-delete flag |
| `category` | `int` | FK → `tx_maiconsent_domain_model_consentcategory.uid` |
| `accepted` | `tinyint unsigned` | `1` if the user accepted, `0` if rejected |
| `session` | `varchar(255)` | Hashed session or FE user ID (no PII) |
| `ip_address` | `varchar(45)` | Anonymized IPv4 (last octet `0`) or IPv6 (last group `0`) |

Records are never updated in place (the `tstamp` column is set only at creation).
Expired records are hard-deleted by the `consent:purge-expired-logs` retention task
(see § 2); the `deleted` soft-delete flag still applies to manual edits in the backend.

---

## 11 · Architecture Constraints

- **No SCSS** — `mai_consent` ships a plain CSS file (`Resources/Public/Css/consent.css`);
  it does not declare `scssphp/scssphp` as a dependency and does not compile SCSS.
  Override styles from `mai_assets` or your site package.
- **No mail dispatch** — `mai_consent` never sends email. It does not depend on
  `maispace/mai-mail` and must not acquire that dependency.
- **No custom category table** — `tx_maiconsent_domain_model_consentcategory` is a
  dedicated consent table; `sys_category` is not used for consent categories.
- **Consent API is write-only** — `POST /api/consent` stores decisions; there is no
  `GET` endpoint that exposes consent state. State is read client-side from the cookie.
- **`marketing` category is frontend-only** — The `marketing` identifier (or any
  other category identifier) in `mai_consent` gates frontend content, never email.
  Email marketing consent lives exclusively in `mai_newsletter`.
- **Depends on `mai_base`** — inherits `AbstractActionController`,
  `AbstractBackendController`, TCA helpers, and the shared icon set.
  Never add an extension-level dependency in the reverse direction.
