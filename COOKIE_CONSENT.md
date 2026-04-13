# Mai Consent — Extension Development Plan

**Extension key:** `mai_consent`  
**Namespace:** `Maispace\MaiConsent`  
**TYPO3:** 13.4 LTS / 14.x · **PHP:** 8.2+  
**Composer:** `maispace/mai-consent`

---

## Table of Contents

1. [Overview & Goals](#1-overview--goals)
2. [Architecture Summary](#2-architecture-summary)
3. [Feature I — Service Definition](#3-feature-i--service-definition)
4. [Feature II — Consent Administration](#4-feature-ii--consent-administration)
5. [Feature III — Content Gating](#5-feature-iii--content-gating)
6. [Database Schema](#6-database-schema)
7. [PHP Class Structure](#7-php-class-structure)
8. [Configuration Files](#8-configuration-files)
9. [JavaScript Architecture](#9-javascript-architecture)
10. [Fluid Templates & ViewHelpers](#10-fluid-templates--viewhelpers)
11. [PSR-14 Events](#11-psr-14-events)
12. [Implementation Phases](#12-implementation-phases)

---

## 1. Overview & Goals

`mai_consent` is a GDPR-compliant cookie/consent management TYPO3 extension. It covers the full consent lifecycle:

- **Service Definition** — Editors define consent categories, individual services (cookies/trackers), and legal texts through the TYPO3 backend.
- **Consent Administration** — Two frontend plugins: a consent modal (presented on first visit) and a preference center (for users to edit their consent at any time, e.g. on the privacy policy page).
- **Content Gating** — A PHP/Fluid/JavaScript API that enables developers of other extensions to wrap external content (maps, videos, embeds) so that it is hidden until the user has consented to the required service.

### Design Principles

- **No external dependencies** at runtime — consent decisions are stored in a first-party cookie (`mai_consent`), no server-side session required.
- **Progressive enhancement** — content gating degrades gracefully; blocked content shows a placeholder with an inline consent trigger.
- **Extensible** — PSR-14 events at every major lifecycle point so integrators can react to consent changes without forking the extension.
- **TYPO3-native** — Extbase models, TCA records, Fluid templates, ES6 modules via importmap. No legacy `RequireJS`, no `ext_localconf.php` module registration.

---

## 2. Architecture Summary

```
┌─────────────────────────────────────────────────────────────────┐
│  TYPO3 BACKEND                                                  │
│                                                                 │
│  Backend Module "Consent Admin"                                 │
│  ├─ Service Management  (list/edit tx_maiconsent_service)       │
│  ├─ Category Management (list/edit tx_maiconsent_category)      │
│  └─ Legal Texts         (list/edit tx_maiconsent_legal_text)    │
└──────────────────────┬──────────────────────────────────────────┘
                       │ TCA Records (Extbase Repositories)
┌──────────────────────▼──────────────────────────────────────────┐
│  FRONTEND PLUGINS                                               │
│                                                                 │
│  Plugin: ConsentModal                                           │
│  └─ Renders on every page; shows banner/modal on first visit    │
│     Fluid template + @maispace/mai-consent/consent-modal.js     │
│                                                                 │
│  Plugin: ConsentPreferences                                     │
│  └─ Placed on privacy policy page; editable service list       │
│     Fluid template + @maispace/mai-consent/consent-prefs.js     │
└──────────────────────┬──────────────────────────────────────────┘
                       │ Cookie: mai_consent (JSON, first-party)
┌──────────────────────▼──────────────────────────────────────────┐
│  CONTENT GATING API                                             │
│                                                                 │
│  PHP   ConsentGate ViewHelper            (Fluid wrapping)       │
│  PHP   ConsentStatus ViewHelper          (inline status badge)  │
│  PHP   ConsentService (PHP utility)      (server-side check)    │
│  JS    ConsentGate module                (client-side reveal)   │
│  Event ConsentChangedEvent               (PSR-14, dispatched    │
│        → third-party extensions react     by JS bridge)        │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Feature I — Service Definition

### 3.1 Concepts

| Concept | Description |
|---|---|
| **Category** | A logical grouping of services (e.g. "Analytics", "Marketing", "Maps"). Has a machine-readable `identifier` (slug), human-readable label, description, and a `is_required` flag. |
| **Service** | A single third-party tool or cookie group (e.g. "Google Analytics", "YouTube", "Leaflet/OSM"). Belongs to one Category. Carries its own label, description, privacy policy URL, and a list of cookie names it sets. |
| **Legal Text** | A versioned block of rich text (e.g. the consent notice wording, privacy policy excerpt). Referenced by the ConsentModal plugin via TypoScript. Allows editors to update legal copy without touching templates. |

### 3.2 TCA Tables

#### `tx_maiconsent_category` *(already scaffolded)*

| Column | Type | Notes |
|---|---|---|
| `title` | input | Required |
| `identifier` | slug | Auto-generated from title, unique in site |
| `description` | text | Shown in modal |
| `is_required` | check | If set, pre-selected and non-deselectable |
| `sorting` | int | Manual sorting |
| *(language/access palettes)* | — | Standard TYPO3 |

#### `tx_maiconsent_service` *(new)*

| Column | Type | Notes |
|---|---|---|
| `title` | input | Required |
| `identifier` | slug | Unique in site |
| `description` | text | Short description for the modal |
| `category` | select (foreign) | → `tx_maiconsent_category` (1:1, required) |
| `privacy_policy_url` | input (link) | URL to provider's privacy policy |
| `cookie_names` | text | Comma-separated cookie name patterns |
| `inject_script` | text | Optional inline `<script>` / `<link>` injected after consent |
| `sorting` | int | — |
| *(language/access)* | — | — |

#### `tx_maiconsent_legal_text` *(new)*

| Column | Type | Notes |
|---|---|---|
| `title` | input | Internal editor label |
| `identifier` | slug | Referenced from TypoScript |
| `body` | rte (text) | Rich-text legal content |
| `version` | input | Optional version string for audit trail |
| *(language/access)* | — | — |

### 3.3 Backend Module — Service Administration

Registered via `Configuration/Backend/Modules.php` (TYPO3 v12+ Module API).  
A single top-level module group `mai_consent` with three sub-modules:

```
Mai Consent (top level icon group)
├── Services      → ServiceController::listAction, editAction
├── Categories    → CategoryController::listAction, editAction
└── Legal Texts   → LegalTextController::listAction, editAction
```

All three sub-modules are simple Extbase list/detail views. Editors can also manage these records directly from the list module (TCA controls this). The backend module serves as a convenient dedicated entry point.

### 3.4 Extbase Domain Models

```
Category          ← already planned
  - title: string
  - identifier: string
  - description: string
  - isRequired: bool

Service
  - title: string
  - identifier: string
  - description: string
  - category: Category      (ObjectStorage / single)
  - privacyPolicyUrl: string
  - cookieNames: string
  - injectScript: string

LegalText
  - title: string
  - identifier: string
  - body: string
  - version: string
```

Persistence mapping in `Configuration/Extbase/Persistence/Classes.php`.

---

## 4. Feature II — Consent Administration

### 4.1 Plugin: ConsentModal

**Purpose:** Presents the consent UI to first-time visitors. Should be placed on every page (via TypoScript `PAGE.10` or a site-wide layout template).

**Behaviour:**
1. On page load, JavaScript checks the `mai_consent` cookie.
2. If no cookie exists (or it is expired), the modal is shown.
3. The modal presents three top-level actions:
   - **Accept All** — consents to every non-required service.
   - **Decline All** — consents only to required services.
   - **Customize** — expands a per-category (and per-service) selection UI.
4. On any choice, the consent decision is written to the `mai_consent` cookie as a JSON structure and the modal is dismissed.
5. A PSR-14 `ConsentSavedEvent` is dispatched via a lightweight AJAX endpoint so the server can optionally log the decision.

**Cookie structure (`mai_consent`):**
```json
{
  "version": 1,
  "timestamp": 1712345678,
  "services": {
    "google-analytics": true,
    "youtube": false,
    "leaflet": true
  }
}
```

The cookie is a flat map of `service.identifier → boolean`. Required services are implicitly `true` and never stored as `false`.

**Plugin registration:**
```php
// ext_localconf.php
ExtensionUtility::configurePlugin(
    'MaiConsent',
    'ConsentModal',
    [ConsentModalController::class => 'index'],
    [ConsentModalController::class => 'index'],   // non-cached (renders personalized state)
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);
```

**Controller:** `ConsentModalController::indexAction`
- Loads all active Categories + their Services via repositories.
- Loads the configured Legal Text (via TypoScript setting `settings.legalTextIdentifier`).
- Assigns them to the Fluid view.
- The actual show/hide logic is entirely client-side; the controller only provides the data.

**Fluid Template structure:**
```
Resources/Private/Templates/ConsentModal/
  Index.html          ← modal shell + action buttons
Resources/Private/Partials/ConsentModal/
  CategoryList.html   ← expandable category + service checkboxes
  ServiceItem.html    ← single service row
  LegalText.html      ← rendered legal body
```

### 4.2 Plugin: ConsentPreferences

**Purpose:** Allows users to review and change their current consent at any time (typically embedded on the privacy policy or legal notice page).

**Behaviour:**
1. Reads the existing `mai_consent` cookie on page load and pre-populates checkboxes.
2. Renders a non-modal, inline form — same category/service structure as the modal's "Customize" view.
3. On submit, writes the updated cookie and dispatches `ConsentSavedEvent`.
4. Does **not** redirect; shows an inline success confirmation.

**Plugin registration:**
```php
ExtensionUtility::configurePlugin(
    'MaiConsent',
    'ConsentPreferences',
    [ConsentPreferencesController::class => 'index', 'save'],
    [ConsentPreferencesController::class => 'save'],   // save is non-cached
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);
```

**Controller:** `ConsentPreferencesController`
- `indexAction` — renders the full preferences form.
- `saveAction` — called via AJAX POST (no full page reload); validates input, returns JSON `{success: true}`.

### 4.3 Consent Cookie Handling

A dedicated `ConsentCookieService` encapsulates all cookie read/write logic:

```php
final class ConsentCookieService
{
    public function read(ServerRequestInterface $request): ConsentState;
    public function write(ConsentState $state, ResponseInterface $response): ResponseInterface;
    public function buildState(array $serviceIdentifiers, bool $acceptAll, bool $declineAll): ConsentState;
}
```

`ConsentState` is a value object:
```php
final readonly class ConsentState
{
    public function __construct(
        public readonly array $services,   // ['google-analytics' => true, ...]
        public readonly int $timestamp,
        public readonly int $version,
    ) {}

    public function hasConsented(string $serviceIdentifier): bool;
    public function hasConsentedForCategory(string $categoryIdentifier): bool;
}
```

### 4.4 AJAX Logging Endpoint

An optional server-side log. Registered as a PSR-15 middleware or Extbase AJAX action.  
Writes a row to `tx_maiconsent_log` (already scaffolded in TCA) with:
- `category` — serialized consent summary (or per-category rows)
- `accepted` — overall boolean
- `session` — hashed session identifier (no PII)
- `ip_address` — anonymized (last octet zeroed)

This is **opt-in** via TypoScript: `settings.enableConsentLogging = 1`.

---

## 5. Feature III — Content Gating

### 5.1 Concept

Content gating wraps third-party content (YouTube embeds, Google Maps iframes, social widgets) in a container that:
1. Hides the actual content until the required service is consented to.
2. Shows a **placeholder** (configurable text/image) with an inline "Accept [service name] and show content" CTA.
3. Once the user consents (either via the inline CTA or the main modal), the actual content is revealed without a page reload.

### 5.2 Fluid ViewHelper — `<consent:gate>`

The primary API for extension developers integrating with mai_consent.

**Usage in any Fluid template:**
```html
<html xmlns:consent="http://typo3.org/ns/Maispace/MaiConsent/ViewHelpers">

<consent:gate service="youtube" placeholderText="Please accept YouTube cookies to view this video.">
    <iframe src="https://www.youtube.com/embed/xyz" ...></iframe>
</consent:gate>
```

**Rendered HTML (server-side):**
```html
<div class="mai-consent-gate"
     data-consent-service="youtube"
     data-consent-state="pending|granted">
    <div class="mai-consent-gate__placeholder" aria-hidden="false">
        <p>Please accept YouTube cookies to view this video.</p>
        <button type="button" class="mai-consent-gate__accept-btn"
                data-consent-accept="youtube">
            Accept YouTube and show content
        </button>
    </div>
    <div class="mai-consent-gate__content" aria-hidden="true">
        <iframe src="https://www.youtube.com/embed/xyz" ...></iframe>
    </div>
</div>
```

The ViewHelper checks `ConsentCookieService::read()` server-side. If consent is already given (returning visitor), it renders the content immediately with `data-consent-state="granted"` — JavaScript only needs to validate, not toggle.

**ViewHelper class:** `Maispace\MaiConsent\ViewHelpers\GateViewHelper`

```php
class GateViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        private readonly ConsentCookieService $consentCookieService,
        private readonly ServiceRepository $serviceRepository,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('service', 'string', 'Service identifier', true);
        $this->registerArgument('placeholderText', 'string', 'Shown when consent is missing', false, '');
        $this->registerArgument('placeholderImage', 'string', 'Optional placeholder image URI', false, '');
        $this->registerArgument('cssClass', 'string', 'Extra CSS class on wrapper', false, '');
    }

    public function render(): string { ... }
}
```

### 5.3 Additional ViewHelpers

| ViewHelper | Purpose |
|---|---|
| `<consent:ifGranted service="youtube">` | Conditional rendering — renders children only if consent is given |
| `<consent:ifDenied service="youtube">` | Inverse of the above |
| `<consent:serviceLabel service="youtube">` | Outputs the service's human-readable title |
| `<consent:openModal>` | Renders a button that opens the consent modal (for custom CTAs) |

### 5.4 PHP Service API

For use in non-Fluid contexts (e.g., DataProcessors, Middleware, other extension PHP code):

```php
use Maispace\MaiConsent\Service\ConsentCheckService;

final class MyDataProcessor implements DataProcessorInterface
{
    public function __construct(
        private readonly ConsentCheckService $consentCheck,
    ) {}

    public function process(...): array
    {
        $request = $GLOBALS['TYPO3_REQUEST'];

        if ($this->consentCheck->hasConsented($request, 'youtube')) {
            // inject full embed
        } else {
            // inject placeholder
        }
    }
}
```

**`ConsentCheckService`** delegates to `ConsentCookieService::read()` and provides:
```php
public function hasConsented(ServerRequestInterface $request, string $serviceIdentifier): bool;
public function hasConsentedForCategory(ServerRequestInterface $request, string $categoryIdentifier): bool;
public function getState(ServerRequestInterface $request): ConsentState;
```

### 5.5 JavaScript Content Gating Module

**`@maispace/mai-consent/consent-gate.js`**

Responsibilities:
- On DOM ready, scan for `[data-consent-gate]` elements.
- For each gated element, check the `mai_consent` cookie.
- If consented → reveal content, hide placeholder.
- If not consented → ensure placeholder is shown.
- Listen for `maiConsent:changed` custom DOM event (dispatched by the modal/prefs JS after any consent update).
- On `maiConsent:changed`, re-scan all gated elements and reveal newly-consented ones.
- Inline "Accept" button inside a placeholder triggers a partial consent (grants the single service) and immediately reveals that element.

```javascript
// @maispace/mai-consent/consent-gate.js
import { ConsentStore } from '@maispace/mai-consent/consent-store.js';

const store = new ConsentStore();

function applyGating() {
    document.querySelectorAll('[data-consent-service]').forEach(gate => {
        const service = gate.dataset.consentService;
        if (store.hasConsented(service)) {
            grant(gate);
        }
    });
}

function grant(gate) {
    gate.querySelector('.mai-consent-gate__placeholder')?.setAttribute('aria-hidden', 'true');
    gate.querySelector('.mai-consent-gate__content')?.setAttribute('aria-hidden', 'false');
    gate.dataset.consentState = 'granted';
}

document.addEventListener('DOMContentLoaded', applyGating);
document.addEventListener('maiConsent:changed', applyGating);
```

### 5.6 TypoScript Integration for Auto-Inject

Services with an `inject_script` value can have their scripts automatically injected into the page after consent is granted. The consent modal JavaScript handles this client-side by reading the consent state and dynamically appending script tags defined in the inline data provided by the ConsentModal plugin.

The `ConsentModalController` embeds the service injection map as a JSON data attribute on the modal container:
```html
<div id="mai-consent-modal"
     data-services='[
       {"identifier":"google-analytics","injectScript":"<script>..."},
       ...
     ]'>
```

The modal JS module reads this map and, after consent is granted, injects the relevant scripts.

---

## 6. Database Schema

```sql
-- Already scaffolded (TCA exists, SQL needed)
CREATE TABLE tx_maiconsent_category (
    uid         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pid         INT(11) NOT NULL DEFAULT 0,
    tstamp      INT(11) NOT NULL DEFAULT 0,
    crdate      INT(11) NOT NULL DEFAULT 0,
    cruser_id   INT(11) NOT NULL DEFAULT 0,
    deleted     SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    hidden      SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    starttime   INT(11) UNSIGNED NOT NULL DEFAULT 0,
    endtime     INT(11) UNSIGNED NOT NULL DEFAULT 0,
    sorting     INT(11) NOT NULL DEFAULT 0,
    sys_language_uid INT(11) NOT NULL DEFAULT 0,
    l10n_parent INT(11) NOT NULL DEFAULT 0,
    l10n_diffsource MEDIUMBLOB,

    title       VARCHAR(255) NOT NULL DEFAULT '',
    identifier  VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    is_required SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,

    PRIMARY KEY (uid),
    KEY parent (pid)
);

-- New: services
CREATE TABLE tx_maiconsent_service (
    uid                 INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pid                 INT(11) NOT NULL DEFAULT 0,
    tstamp              INT(11) NOT NULL DEFAULT 0,
    crdate              INT(11) NOT NULL DEFAULT 0,
    cruser_id           INT(11) NOT NULL DEFAULT 0,
    deleted             SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    hidden              SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    starttime           INT(11) UNSIGNED NOT NULL DEFAULT 0,
    endtime             INT(11) UNSIGNED NOT NULL DEFAULT 0,
    sorting             INT(11) NOT NULL DEFAULT 0,
    sys_language_uid    INT(11) NOT NULL DEFAULT 0,
    l10n_parent         INT(11) NOT NULL DEFAULT 0,
    l10n_diffsource     MEDIUMBLOB,

    title               VARCHAR(255) NOT NULL DEFAULT '',
    identifier          VARCHAR(255) NOT NULL DEFAULT '',
    description         TEXT,
    category            INT(11) NOT NULL DEFAULT 0,
    privacy_policy_url  VARCHAR(2048) NOT NULL DEFAULT '',
    cookie_names        TEXT,
    inject_script       TEXT,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY category (category)
);

-- New: legal texts
CREATE TABLE tx_maiconsent_legal_text (
    uid              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pid              INT(11) NOT NULL DEFAULT 0,
    tstamp           INT(11) NOT NULL DEFAULT 0,
    crdate           INT(11) NOT NULL DEFAULT 0,
    cruser_id        INT(11) NOT NULL DEFAULT 0,
    deleted          SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    hidden           SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    starttime        INT(11) UNSIGNED NOT NULL DEFAULT 0,
    endtime          INT(11) UNSIGNED NOT NULL DEFAULT 0,
    sys_language_uid INT(11) NOT NULL DEFAULT 0,
    l10n_parent      INT(11) NOT NULL DEFAULT 0,
    l10n_diffsource  MEDIUMBLOB,

    title            VARCHAR(255) NOT NULL DEFAULT '',
    identifier       VARCHAR(255) NOT NULL DEFAULT '',
    body             MEDIUMTEXT,
    version          VARCHAR(50) NOT NULL DEFAULT '',

    PRIMARY KEY (uid),
    KEY parent (pid)
);

-- Already scaffolded (TCA exists, SQL needed)
CREATE TABLE tx_maiconsent_log (
    uid         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pid         INT(11) NOT NULL DEFAULT 0,
    tstamp      INT(11) NOT NULL DEFAULT 0,
    crdate      INT(11) NOT NULL DEFAULT 0,
    cruser_id   INT(11) NOT NULL DEFAULT 0,
    deleted     SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,

    category    INT(11) NOT NULL DEFAULT 0,
    accepted    SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    session     VARCHAR(255) NOT NULL DEFAULT '',
    ip_address  VARCHAR(45) NOT NULL DEFAULT '',

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY crdate (crdate)
);
```

---

## 7. PHP Class Structure

```
Classes/
│
├── Controller/
│   ├── ConsentModalController.php         # Frontend plugin: modal
│   ├── ConsentPreferencesController.php   # Frontend plugin: preferences page
│   └── Backend/
│       ├── ServiceController.php          # Backend module: services
│       ├── CategoryController.php         # Backend module: categories
│       └── LegalTextController.php        # Backend module: legal texts
│
├── Domain/
│   ├── Model/
│   │   ├── Category.php
│   │   ├── Service.php
│   │   ├── LegalText.php
│   │   └── ConsentLog.php
│   └── Repository/
│       ├── CategoryRepository.php
│       ├── ServiceRepository.php
│       ├── LegalTextRepository.php
│       └── ConsentLogRepository.php
│
├── Service/
│   ├── ConsentCookieService.php           # Cookie read/write, cookie name constant
│   ├── ConsentCheckService.php            # Public API: hasConsented(), getState()
│   └── ConsentLogService.php             # Writes tx_maiconsent_log rows (opt-in)
│
├── ValueObject/
│   └── ConsentState.php                   # Immutable: services[], timestamp, version
│
├── ViewHelpers/
│   ├── GateViewHelper.php                 # <consent:gate service="...">
│   ├── IfGrantedViewHelper.php            # <consent:ifGranted service="...">
│   ├── IfDeniedViewHelper.php             # <consent:ifDenied service="...">
│   ├── ServiceLabelViewHelper.php         # <consent:serviceLabel service="...">
│   └── OpenModalViewHelper.php            # <consent:openModal>
│
├── Event/
│   ├── ConsentSavedEvent.php              # Dispatched after consent is written
│   ├── BeforeConsentSavedEvent.php        # Dispatched before write (allows modification)
│   └── ConsentGateRenderedEvent.php       # Dispatched when a gate VH renders
│
└── EventListener/
    └── ConsentLogListener.php             # Listens to ConsentSavedEvent → writes log
```

---

## 8. Configuration Files

### 8.1 `ext_localconf.php`

```php
<?php
declare(strict_types=1);

use Maispace\MaiConsent\Controller\ConsentModalController;
use Maispace\MaiConsent\Controller\ConsentPreferencesController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::configurePlugin(
    'MaiConsent',
    'ConsentModal',
    [ConsentModalController::class => 'index'],
    [ConsentModalController::class => 'index'],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'MaiConsent',
    'ConsentPreferences',
    [ConsentPreferencesController::class => 'index', 'save'],
    [ConsentPreferencesController::class => 'save'],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);
```

### 8.2 `Configuration/Backend/Modules.php`

```php
<?php
use Maispace\MaiConsent\Controller\Backend\CategoryController;
use Maispace\MaiConsent\Controller\Backend\ServiceController;
use Maispace\MaiConsent\Controller\Backend\LegalTextController;

return [
    'mai_consent' => [
        'labels' => 'LLL:EXT:mai_consent/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'ext-maispace-mai_consent',
    ],
    'mai_consent_services' => [
        'parent' => 'mai_consent',
        'access' => 'user',
        'path' => '/module/mai-consent/services',
        'iconIdentifier' => 'ext-maispace-mai_consent-service',
        'labels' => 'LLL:EXT:mai_consent/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'MaiConsent',
        'controllerActions' => [
            ServiceController::class => ['list', 'show'],
        ],
    ],
    'mai_consent_categories' => [
        'parent' => 'mai_consent',
        'access' => 'user',
        'path' => '/module/mai-consent/categories',
        'iconIdentifier' => 'ext-maispace-mai_consent-category',
        'labels' => 'LLL:EXT:mai_consent/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'MaiConsent',
        'controllerActions' => [
            CategoryController::class => ['list', 'show'],
        ],
    ],
    'mai_consent_legal' => [
        'parent' => 'mai_consent',
        'access' => 'user',
        'path' => '/module/mai-consent/legal',
        'iconIdentifier' => 'ext-maispace-mai_consent-legal',
        'labels' => 'LLL:EXT:mai_consent/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'MaiConsent',
        'controllerActions' => [
            LegalTextController::class => ['list', 'show'],
        ],
    ],
];
```

### 8.3 `Configuration/Extbase/Persistence/Classes.php`

```php
<?php
return [
    \Maispace\MaiConsent\Domain\Model\Category::class => [
        'tableName' => 'tx_maiconsent_category',
    ],
    \Maispace\MaiConsent\Domain\Model\Service::class => [
        'tableName' => 'tx_maiconsent_service',
    ],
    \Maispace\MaiConsent\Domain\Model\LegalText::class => [
        'tableName' => 'tx_maiconsent_legal_text',
    ],
    \Maispace\MaiConsent\Domain\Model\ConsentLog::class => [
        'tableName' => 'tx_maiconsent_log',
    ],
];
```

### 8.4 `Configuration/JavaScriptModules.php`

```php
<?php
return [
    'dependencies' => ['core'],
    'imports' => [
        '@maispace/mai-consent/' => 'EXT:mai_consent/Resources/Public/JavaScript/',
    ],
];
```

### 8.5 TypoScript (`Configuration/TypoScript/setup.typoscript`)

```typoscript
plugin.tx_maiconsent_consentmodal {
    settings {
        # UID of the storage page holding the consent records
        storagePid = {$plugin.tx_maiconsent.storagePid}

        # Identifier of the legal text to display in the modal
        legalTextIdentifier = consent-notice

        # Cookie lifetime in days
        cookieLifetime = 365

        # SameSite attribute: Lax | Strict | None
        cookieSameSite = Lax

        # Enable server-side logging (0/1)
        enableConsentLogging = 0
    }
}

plugin.tx_maiconsent_consentpreferences {
    settings {
        storagePid = {$plugin.tx_maiconsent.storagePid}
    }
}
```

`Configuration/TypoScript/constants.typoscript`:
```typoscript
plugin.tx_maiconsent {
    # cat=plugin.tx_maiconsent/basic/10; type=int+; label=Storage PID for consent records
    storagePid = 0
}
```

---

## 9. JavaScript Architecture

### Modules

| Module | Path | Purpose |
|---|---|---|
| `consent-store.js` | `Resources/Public/JavaScript/` | Read/write `mai_consent` cookie; single source of truth |
| `consent-modal.js` | `Resources/Public/JavaScript/` | Modal show/hide, accept-all / decline-all / customize flow |
| `consent-prefs.js` | `Resources/Public/JavaScript/` | Preferences form pre-population and submit |
| `consent-gate.js` | `Resources/Public/JavaScript/` | Content gating reveal logic |

### `ConsentStore` (shared module)

```javascript
// consent-store.js
export class ConsentStore {
    #COOKIE_NAME = 'mai_consent';
    #VERSION = 1;

    read() { /* parse JSON cookie → ConsentState */ }
    write(services) { /* serialize → set cookie */ }
    hasConsented(serviceIdentifier) { /* bool */ }
    hasConsentedForCategory(categoryIdentifier, serviceMap) { /* bool */ }
    acceptAll(serviceIdentifiers) { /* write all true */ }
    declineAll(requiredIdentifiers) { /* write non-required false */ }
    dispatch() {
        document.dispatchEvent(new CustomEvent('maiConsent:changed', {
            detail: this.read()
        }));
    }
}
```

### `consent-modal.js`

- Loaded globally (injected by `ConsentModalController` via `f:asset.script`).
- On `DOMContentLoaded`: if no valid cookie → show modal container.
- Binds "Accept All", "Decline All", "Customize" buttons.
- In "Customize" mode, renders category/service checkboxes from embedded JSON data.
- On save: calls `ConsentStore.write()` + `ConsentStore.dispatch()`.
- Optionally posts to the AJAX log endpoint if `enableConsentLogging` is enabled.

### DOM Event Contract

All JavaScript components communicate via the `maiConsent:changed` CustomEvent on `document`:
```javascript
{
    detail: {
        version: 1,
        timestamp: 1712345678,
        services: { "youtube": true, "google-analytics": false }
    }
}
```

This makes the consent system observable from any third-party extension's own JavaScript without coupling to internal APIs.

---

## 10. Fluid Templates & ViewHelpers

### Template Structure

```
Resources/Private/
├── Language/
│   ├── Default/
│   │   ├── locallang.xlf           # Frontend labels
│   │   ├── locallang_tca.xlf       # Backend TCA labels (already referenced)
│   │   └── locallang_mod.xlf       # Backend module labels
├── Layouts/
│   ├── Default.html                # Minimal frontend layout
│   └── Backend.html                # Backend module layout with nav
├── Templates/
│   ├── ConsentModal/
│   │   └── Index.html
│   ├── ConsentPreferences/
│   │   ├── Index.html
│   │   └── Save.html               # AJAX response fragment
│   └── Backend/
│       ├── Service/
│       │   ├── List.html
│       │   └── Show.html
│       ├── Category/
│       │   ├── List.html
│       │   └── Show.html
│       └── LegalText/
│           ├── List.html
│           └── Show.html
└── Partials/
    ├── ConsentModal/
    │   ├── CategoryList.html
    │   ├── ServiceItem.html
    │   └── LegalText.html
    └── ConsentPreferences/
        ├── CategoryGroup.html
        └── ServiceCheckbox.html
```

### ViewHelper Namespace

Registered globally via TypoScript so templates don't need manual `xmlns` declarations in every file:

```typoscript
config.fluid.namespaces.consent = Maispace\MaiConsent\ViewHelpers
```

### ViewHelper Usage Examples

**Content gating (other extension's Fluid template):**
```html
<consent:gate service="youtube" placeholderText="Accept YouTube to watch this video.">
    <f:media file="{file}" />
</consent:gate>
```

**Conditional rendering:**
```html
<consent:ifGranted service="leaflet">
    <f:render partial="Map/LeafletMap" />
</consent:ifGranted>
<consent:ifDenied service="leaflet">
    <p>Enable the Maps category to view our interactive map.</p>
    <consent:openModal label="Manage cookie settings" />
</consent:ifDenied>
```

---

## 11. PSR-14 Events

| Event Class | When Dispatched | Mutable? | Use Case |
|---|---|---|---|
| `BeforeConsentSavedEvent` | Before writing cookie | ✅ Yes — can modify `ConsentState` | Enforce additional required services |
| `ConsentSavedEvent` | After cookie written | ❌ No | Send analytics, write audit log |
| `ConsentGateRenderedEvent` | When `<consent:gate>` renders | ✅ Yes — can override placeholder HTML | Custom placeholder per service |
| `ConsentModalDataEvent` | Before modal data assigned to view | ✅ Yes — add/modify categories or services | A/B test category grouping |

**Example: Custom listener in a third-party extension**

```php
// Configuration/Services.yaml (in the integrating extension)
MyVendor\MyExt\EventListener\AfterConsentListener:
  tags:
    - name: event.listener
      event: Maispace\MaiConsent\Event\ConsentSavedEvent
      identifier: 'my-ext/consent-saved'
```

```php
// Classes/EventListener/AfterConsentListener.php
#[AsEventListener(identifier: 'my-ext/consent-saved')]
final class AfterConsentListener
{
    public function __invoke(ConsentSavedEvent $event): void
    {
        $state = $event->getConsentState();
        if ($state->hasConsented('matomo')) {
            // fire Matomo tracking
        }
    }
}
```

---

## 12. Implementation Phases

### Phase 1 — Foundation (Backend + Data Model)

**Goal:** Editors can manage services, categories, and legal texts. No frontend yet.

- [ ] Create `ext_tables.sql` with all four tables
- [ ] Create TCA for `tx_maiconsent_service` and `tx_maiconsent_legal_text`
- [ ] Create Extbase Domain Models: `Category`, `Service`, `LegalText`, `ConsentLog`
- [ ] Create Repositories for all four models
- [ ] Create `Configuration/Extbase/Persistence/Classes.php`
- [ ] Register Backend Module (`Configuration/Backend/Modules.php`)
- [ ] Implement `Backend/ServiceController`, `CategoryController`, `LegalTextController`
- [ ] Create backend Fluid templates (List/Show for each)
- [ ] Add icon SVGs for all records and backend module
- [ ] Add XLF language files (`locallang_tca.xlf`, `locallang_mod.xlf`)

### Phase 2 — Consent Modal Plugin

**Goal:** Consent modal is shown on first visit; decision is stored in cookie.

- [ ] Implement `ConsentCookieService` + `ConsentState` value object
- [ ] Implement `ext_localconf.php` with both plugin registrations
- [ ] Implement `ConsentModalController::indexAction`
- [ ] Create `Configuration/TypoScript/` (setup + constants)
- [ ] Create frontend Fluid templates for ConsentModal
- [ ] Create `consent-store.js` ES6 module
- [ ] Create `consent-modal.js` ES6 module
- [ ] Register JS modules via `Configuration/JavaScriptModules.php`
- [ ] Define PSR-14 `ConsentSavedEvent` + `BeforeConsentSavedEvent`
- [ ] Wire `ConsentLogListener` event listener (opt-in logging)
- [ ] Basic CSS for modal (scoped, BEM naming: `mai-consent-*`)

### Phase 3 — Consent Preferences Plugin

**Goal:** Users can edit consent from the privacy policy page.

- [ ] Implement `ConsentPreferencesController` (index + save actions)
- [ ] Create preferences Fluid templates
- [ ] Create `consent-prefs.js` ES6 module (reads existing cookie, posts AJAX save)
- [ ] Wire `maiConsent:changed` event dispatch after save

### Phase 4 — Content Gating API

**Goal:** Third-party extensions can gate their content behind consent.

- [ ] Implement `ConsentCheckService` (public PHP API)
- [ ] Implement `GateViewHelper`
- [ ] Implement `IfGrantedViewHelper`, `IfDeniedViewHelper`, `ServiceLabelViewHelper`, `OpenModalViewHelper`
- [ ] Create `consent-gate.js` ES6 module
- [ ] Register global Fluid namespace via TypoScript
- [ ] Define `ConsentGateRenderedEvent`
- [ ] Write integration documentation (usage examples for other extension developers)

### Phase 5 — Polish & Hardening

- [ ] Accessibility audit: modal focus trap, ARIA attributes, keyboard navigation
- [ ] Add `respectDnt` support (check `navigator.doNotTrack` in JS)
- [ ] Finalize cookie attributes: `SameSite`, `Secure`, `domain` from TypoScript settings
- [ ] PHPUnit tests: `ConsentCookieService`, `ConsentState`, `ConsentCheckService`
- [ ] JavaScript tests (Vitest or Jest): `ConsentStore`
- [ ] PHPStan baseline cleanup
- [ ] Full XLF translation files (DE minimum)
- [ ] Update `README.md` with full usage documentation

---

*Last updated: 2026-04-13*
