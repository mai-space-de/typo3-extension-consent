# maispace/consent — Consent Management for TYPO3

[![CI](https://github.com/mai-space-de/typo3-extension-consent/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/mai-space-de/typo3-extension-consent/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20LTS-orange)](https://typo3.org/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

A TYPO3 extension that adds a cookie banner and consent modal to the frontend, lets editors assign content elements to consent categories in the backend, and provides administrators with a module to manage categories and view consent statistics — fully GDPR-compliant.

**Requires:** TYPO3 13.4 LTS · PHP 8.2+

---

## Features at a glance

| Feature | API |
|---|---|
| Consent category assignment on every content element | Backend content element tab |
| Cookie banner with accept / reject / customize actions | Auto-injected via middleware |
| Consent modal for per-category preference management | Triggered from banner |
| Consent preferences stored in a first-party cookie | `mai_consent` cookie |
| Content elements hidden until consent is granted | DataProcessor + JavaScript runtime |
| JavaScript runtime for full client-side consent UX | `Resources/Public/JavaScript/consent.js` (registered via `maispace/assets` ViewHelpers) |
| CSS styling for banner, modal and placeholder | `Resources/Public/Css/consent.css` (registered via `maispace/assets` ViewHelpers) |
| Backend module for managing categories | `Web > Consent` module |
| Consent statistics per category (tables + progress bar) | Backend module dashboard |
| CSV export of consent statistics | One-click download from statistics view |
| Fully customizable banner and modal text via TypoScript | `plugin.tx_mai_consent` |
| All frontend texts translatable via XLIFF | `locallang_fe.xlf` |
| GDPR-compliant — no data leaves the server | Built-in |

---

## Installation

```bash
composer require maispace/consent
```

Include the TypoScript setup in your site package:

```typoscript
@import 'EXT:mai_consent/Configuration/TypoScript/setup.typoscript'
```

The cookie banner and middleware are registered automatically. CSS and JavaScript are
included via [`maispace/assets`](https://github.com/mai-space-de/typo3-extension-assets)
ViewHelpers through TYPO3's AssetCollector — no manual PageRenderer wiring required.

Run the Database Analyser in **Admin Tools → Database Analyser** to create the two extension tables after installation.

---

## Content Element Integration

The extension adds a **Consent** tab to every content element in the TYPO3 backend. Editors can assign one or more consent categories to each element. If a user has not granted consent for any of the assigned categories, the element is hidden on the frontend.

Elements with no category assigned are always rendered regardless of user consent.

**Assigning categories:**

1. Open any content element in the backend
2. Switch to the **Consent** tab
3. Select one or more categories from the multi-select list
4. Save — the element is now consent-gated

**Rendered markup:**

```html
<!-- content element with category UID 2 assigned -->
<div data-maispace-consent-required="2"
     data-maispace-consent-uid="<element-uid>"
     hidden="hidden">
    <!-- original content element markup -->
</div>
```

The JavaScript runtime reads the cookie on page load and reveals elements whose required categories have been accepted. When consent changes, elements are shown or hidden immediately without a page reload.

---

## Cookie Banner

The banner is injected automatically into every frontend page response via `ConsentBannerMiddleware`. It appears on first visit and whenever the user's stored preferences need re-confirmation (e.g. after categories are added).

**Banner actions:**

| Button | Behaviour |
|---|---|
| Accept all | Grants consent for all active categories |
| Reject all | Revokes consent for all non-essential categories |
| Customize | Opens the consent modal |

The banner is rendered from a Fluid partial. Override it in your site package:

```
EXT:my_sitepackage/Resources/Private/Partials/Consent/Banner.html
```

Configure the partial path via TypoScript:

```typoscript
plugin.tx_mai_consent.view {
    partialRootPaths.10 = EXT:my_sitepackage/Resources/Private/Partials/
}
```

**Banner positions:** `bottom` (default) · `top` · `bottom-left` · `bottom-right`

---

## Consent Modal

The modal is opened from the cookie banner's **Customize** button (or any element on the page with `data-maispace-consent-action="open-modal"`). It lists all active categories with their description and an individual toggle per category.

```html
<!-- Open the consent modal from any element -->
<button data-maispace-consent-action="open-modal">Manage preferences</button>
```

Override the modal partial in your site package:

```
EXT:my_sitepackage/Resources/Private/Partials/Consent/Modal.html
```

The modal partial receives the following variables:

| Variable | Type | Description |
|---|---|---|
| `{categories}` | array | All active consent categories |
| `{settings.modal.showCategoryDescriptions}` | bool | Whether to show category descriptions |

**Accessibility:** The modal traps focus while open and restores focus on close. Pressing `Escape` closes the modal. Essential categories are marked with a badge and their checkboxes are disabled.

---

## Consent Storage

User preferences are stored in a single first-party cookie named `mai_consent`. The cookie contains a JSON object keyed by category UID:

```json
{ "1": true, "2": false, "3": true }
```

| Property | Value |
|---|---|
| Name | `mai_consent` |
| Lifetime | 365 days (configurable) |
| Scope | Current domain, path `/` |
| `SameSite` | `Lax` |
| `Secure` | Set automatically on HTTPS |
| `HttpOnly` | `false` — JavaScript must read preferences to gate content |

The cookie is set entirely client-side via JavaScript after the user interacts with the banner or modal. No consent data is transmitted to the server or any third party.

---

## Frontend Gating

Content elements assigned to a consent category are hidden on initial render and revealed by the JavaScript runtime when the required consent is present.

A placeholder can be shown instead of the blank space:

```typoscript
plugin.tx_mai_consent.gating {
    placeholder = 1
    placeholderPartial = Consent/Placeholder
}
```

---

## Backend Module

The **Consent** backend module (`Web > Consent`) gives administrators full control over consent categories and visibility into user preferences:

**Category management**

- Create, edit, and delete consent categories
- Set name, description, and whether the category is essential (essential categories cannot be rejected)

**Statistics**

- Total consent events recorded per category
- Accept / reject ratio per category as a progress bar
- Daily consent activity over the last 30 days
- **CSV export** — download a spreadsheet of all category statistics

---

## TypoScript Configuration

```typoscript
plugin.tx_mai_consent {
    cookie {
        name = mai_consent      # cookie name
        lifetime = 365               # days until the cookie expires
        sameSite = Lax               # Strict | Lax | None
    }
    banner {
        enable = 1                   # 0 to suppress banner globally (e.g. in dev)
        position = bottom            # bottom | top | bottom-left | bottom-right
        showOnEveryPage = 0          # 1 = re-show banner on every page if not all categories decided
    }
    modal {
        showCategoryDescriptions = 1 # 0 to hide descriptions in the modal toggle list
    }
    gating {
        placeholder = 0              # 1 = render a placeholder for hidden elements
        placeholderPartial = Consent/Placeholder
    }
    statistics {
        enable = 1                   # 0 to disable consent event recording entirely
        retentionDays = 90           # delete events older than N days (0 = keep forever)
    }
    view {
        templateRootPaths.0 = EXT:mai_consent/Resources/Private/Templates/
        partialRootPaths.0  = EXT:mai_consent/Resources/Private/Partials/
        layoutRootPaths.0   = EXT:mai_consent/Resources/Private/Layouts/
    }
}
```

---

## PSR-14 Events

| Event | When |
|---|---|
| `BeforeBannerRenderedEvent` | Before the banner HTML is assembled — modify template variables or veto output |
| `AfterBannerRenderedEvent` | After the banner HTML is built — inspect or replace the output string |
| `BeforeConsentStoredEvent` | Before a user's preference change is written — validate or modify values |
| `AfterConsentStoredEvent` | After preferences are stored — trigger downstream actions (analytics opt-in, etc.) |
| `BeforeContentElementGatedEvent` | Per gated content element — inspect or override the category requirement |

Example listener registration:

```yaml
# EXT:my_sitepackage/Configuration/Services.yaml
services:
  MyVendor\MySitepackage\EventListener\ModifyBanner:
    tags:
      - name: event.listener
        identifier: 'my-sitepackage/modify-banner'
        event: Maispace\MaiConsent\Event\BeforeBannerRenderedEvent
```

---

## Internationalization

All visible frontend texts (banner, modal, placeholder) are defined in
`Resources/Private/Language/locallang_fe.xlf` and referenced via
`<f:translate>` in Fluid templates. Provide TYPO3 language override files
in your site configuration to translate them.

---

## Development

### Running tests

```bash
composer install
composer test
```

Or verbose:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --testdox
```

**Test structure:**

| File | What it tests |
|---|---|
| `Tests/Unit/Service/ConsentCookieServiceTest.php` | Cookie parsing, preference read/write, essential category handling |
| `Tests/Unit/Service/CategoryServiceTest.php` | Category CRUD, ordering, essential flag |
| `Tests/Unit/Middleware/ConsentBannerMiddlewareTest.php` | Banner injection, CSS/JS injection, suppression conditions |
| `Tests/Unit/DataProcessing/ConsentGatingProcessorTest.php` | Element gating, skip event, category UID override |

All tests are pure unit tests — no database, no TYPO3 installation required.

### CI

| Job | What it checks |
|---|---|
| `composer-validate` | `composer.json` is valid and well-formed |
| `unit-tests` | PHPUnit suite across PHP 8.2 / 8.3 × TYPO3 13.4 |
| `static-analysis` | PHPStan (`phpstan.neon`, level max) |
| `code-style` | EditorConfig + PHP-CS-Fixer |
| `typoscript-lint` | TypoScript style/structure |

---

## License

GPL-2.0-or-later
