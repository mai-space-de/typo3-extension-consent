# Next Steps — EXT:mai_consent

## 1. TYPO3 Integration

### 1.1 Database setup
Run the TYPO3 database analyser after installing the extension to create the `tx_maiconsent_category` and `tx_maiconsent_log` tables:

```
Admin Tools → Maintenance → Analyse Database Structure → Apply
```

### 1.2 Create seed consent categories
In the TYPO3 backend, create a storage page and add at least the following categories via **List** module:

| Title | Identifier | Required |
|---|---|---|
| Essential | essential | ✅ |
| Analytics | analytics | ☐ |
| Marketing | marketing | ☐ |
| Maps | mapping | ☐ |

### 1.3 TypoScript constants
Set the storage page uid in the site's TypoScript constants:

```typoscript
plugin.tx_maiconsent.settings.storagePageId = <uid>
```

### 1.4 Insert banner plugin
Add the **Consent Banner** content element to the page layout that renders on every page (e.g. the root page or a shared include). The banner must render before `</body>`.

---

## 2. Leaflet Map Integration

The `ContentGateViewHelper` is ready. Wrap the map partial with it in the relevant template:

```html
<consent:contentGate category="mapping" placeholder="Please accept the Maps category to view the map.">
    <f:render partial="Map/Leaflet" arguments="{_all}" />
</consent:contentGate>
```

The JS will reveal the content automatically once the user grants consent to the `mapping` category. The Leaflet tile layer should only be initialised after `window.MaiConsent.hasConsent('mapping')` returns `true` — hook into the `DOMContentLoaded` event or listen for the custom event dispatched by `consent.js` (see item 4 below).

---

## 3. Missing: Frontend Controller in ext_localconf.php

The `BannerController` is registered as a content element plugin in `ext_localconf.php`. The Fluid template path resolves to `Resources/Private/Templates/Frontend/Banner/Index.html`. Verify the rendering path works end-to-end after inserting the plugin on a page.

---

## 4. Recommended Enhancements

### 4.1 Dispatch a custom DOM event after consent is saved
In `consent.js`, after `applyConsent()` is called, dispatch a `CustomEvent` so third-party scripts (e.g. Leaflet initialiser) can react without polling:

```js
window.dispatchEvent(new CustomEvent('maiConsent:updated', { detail: categories }));
```

### 4.2 Re-consent on category change
If a new consent category is added to the database, existing visitors will not see the banner again because their cookie is still valid. Add a `version` field to the cookie payload and bump a constant in `consent.js` to force re-consent when categories change.

### 4.3 Consent withdrawal
Currently consent can only be given, not withdrawn after the fact without clearing the cookie. Add a "Manage cookies" link (e.g. in the footer) that reopens the modal and allows the user to change preferences.

### 4.4 Anonymisation config
IP anonymisation is hard-coded in `ConsentApiMiddleware::anonymizeIp()`. Move the strategy (full IP, last-octet, none) to a TypoScript setting so it can be configured per site.

---

## 5. QA

### 5.1 PHPStan
```bash
composer check:phpstan
```
Expected issues to resolve:
- `ConsentLogRepository::countPerCategory()` uses `GeneralUtility::makeInstance` directly; add `@phpstan-ignore` or refactor to `ConnectionPool` injection via constructor.
- `BannerController` parent constructor signature may differ across `mai_base` versions — verify.

### 5.2 PHPCS
```bash
composer lint:fix
```

### 5.3 Manual smoke test checklist
- [ ] Banner appears on first visit (no cookie)
- [ ] "Accept all" sets cookie and hides banner
- [ ] "Reject all" sets cookie and hides banner
- [ ] "Customize" opens modal, save works
- [ ] Gated content is hidden until consent is granted
- [ ] Map does not load Leaflet tiles before `mapping` consent
- [ ] POST `/api/consent` returns `{"success":true}` and writes log rows
- [ ] Backend statistics module shows correct acceptance rates
- [ ] Revisiting the page with existing cookie skips the banner

---

## 6. Accessibility

- The banner `role="dialog"` + `aria-modal="true"` is in place. Add focus trapping inside the modal (Tab key should cycle within the dialog).
- Ensure the banner is announced by screen readers on appearance (use `aria-live="polite"` on the banner container or move focus to it).

---

## 7. Optional: Translations

The language file `locallang.xlf` contains English source strings. Add translation files for the site languages:

- `Resources/Private/Language/de.locallang.xlf` (German)
- `Resources/Private/Language/uk.locallang.xlf` (Ukrainian)
- `Resources/Private/Language/ar.locallang.xlf` (Arabic — note RTL layout adjustments needed in CSS)
