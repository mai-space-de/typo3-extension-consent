# TYPO3 Cookie Consent Extension — Implementation Plan

## Goals

1. Provide a complete, GDPR-compliant consent lifecycle: modal, update, audit, withdrawal, and data download.
2. Give editors and integrators native TYPO3 tooling to configure, discover, and gate third-party scripts and content elements without custom development.

---

## Phase 1 — Foundation

### 1.1 Extension Skeleton & Database Schema

- [ ] Define `ext_tables.sql` with all tables:
  - `tx_cookieconsent_category` — consent categories (Strictly Necessary, Functional, Analytics, Marketing)
  - `tx_cookieconsent_cookie` — individual cookie records (name, purpose, provider, duration, type, legal basis)
  - `tx_cookieconsent_tag` — Tag Registry records (see §6.1)
  - `tx_cookieconsent_log` — append-only audit log (timestamp, policy version hash, categories, user agent, optional IP / fe_user UID)
- [ ] Register TCA for all tables with full `sys_language_uid` support
- [ ] Configure MM relation between `tx_cookieconsent_tag` and site roots
- [ ] Add optional `fe_users` field for cross-device consent persistence

### 1.2 Core PHP Services

| Service | Responsibility |
|---|---|
| `ConsentManager` | Central orchestrator; public API for all consent operations |
| `ConsentStorageService` | Read / write consent cookie and optional DB record |
| `ConsentVersionService` | Policy versioning — hash of active category/cookie config |
| `CategoryRegistry` | Loads cookie and category config per site |
| `ConsentAuditLog` | Immutable append-only log writer |

- [ ] Implement `ConsentStorageService`: write HTTP cookie (`cookieconsent_state`) with correct `SameSite`, `Secure`, and domain scope; write optional DB record
- [ ] Implement `ConsentVersionService`: derive version hash from active category/cookie records; trigger re-prompt when hash changes
- [ ] Implement `CategoryRegistry`: load records per site root, respecting `sys_language_uid` fallback chain
- [ ] Implement `ConsentAuditLog`: append-only DB writes, never update in place

### 1.3 PSR-14 Events

- [ ] `ConsentGrantedEvent` — fired when user accepts one or more categories
- [ ] `ConsentWithdrawnEvent` — fired on category or full withdrawal
- [ ] `ConsentModalRenderedEvent` — fired before modal HTML is output
- [ ] `TagDiscoveredEvent` — fired when discovery engine finds an unregistered script
- [ ] `TagActivatedEvent` — fired when a gated Tag record is unblocked post-consent

Register all events in `Configuration/Services.yaml`.

---

## Phase 2 — Consent Modal & UI

### 2.1 Server-Side Rendering

- [ ] Fluid layout partial: `ConsentBanner` — minimal first-layer banner (accept all / manage)
- [ ] Fluid layout partial: `ConsentModal` — full detail view with per-category and per-cookie toggles
- [ ] Inject synchronous `<head>` block via Fluid layout:
  - Google Consent Mode v2 defaults (`gtag('consent', 'default', ...)`)
  - `T3Consent` singleton bootstrap from cookie state
- [ ] ViewHelper `consent:renderTags(position: 'head'|'body_end')` for Tag Registry injection
- [ ] ViewHelper / Fluid partial `ConsentTrigger` for re-opening modal (footer link, floating button)

### 2.2 Accessibility Requirements

- [ ] `role="dialog"`, `aria-modal="true"`, `aria-labelledby` on modal root
- [ ] Focus trap inside modal when open; return focus to trigger on close
- [ ] Toggle switches: `role="switch"`, `aria-checked` updated on state change
- [ ] Full `Tab` / `Shift+Tab` keyboard navigation; `Escape` closes modal
- [ ] `prefers-reduced-motion` media query respected for all animations
- [ ] WCAG 2.1 AA colour contrast baseline
- [ ] Visually hidden labels on icon-only buttons

### 2.3 Editor Customisation

- [ ] FlexForm or dedicated records for: banner text, button labels, colour scheme class, logo
- [ ] Per-site enable/disable of categories
- [ ] Cookie records attachable to site root or specific pages
- [ ] Backend preview mode for testing modal appearance

---

## Phase 3 — JavaScript Layer

### 3.1 Module Structure

All modules are vanilla JS ES modules, bundled via esbuild/rollup into a single entry point. Tree-shakeable.

| Module | Responsibility |
|---|---|
| `consent-store.js` | Read / write consent cookie and localStorage mirror |
| `modal.js` | Banner and full modal UI, focus trap, keyboard navigation |
| `script-unlocker.js` | Activates `<script type="text/plain">` tags post-consent |
| `tag-registry.js` | Loads and activates Tag Registry records dynamically |
| `event-bus.js` | Dispatches and subscribes to `typo3consent:*` DOM events |
| `t3consent-api.js` | `T3Consent` global singleton (`has`, `onGrant`, `onWithdraw`) |
| `gtm-bridge.js` | Bootstraps GTM; manages `dataLayer` `consent_update` pushes |
| `placeholder.js` | Manages CE placeholder swap-in on consent grant |

### 3.2 T3Consent API

```js
window.T3Consent = {
  has:       (category) => /* sync read from cookie/localStorage */,
  onGrant:   (category, fn) => /* fires fn immediately if already granted,
                                  otherwise queues for grant event */,
  onWithdraw:(category, fn) => /* fires fn when category is withdrawn */,
};
```

- [ ] `has()` reads synchronously from cookie/localStorage — no async, no flash of ungated content
- [ ] `onGrant()` fires callback immediately if consent was given in a prior session
- [ ] Initialised from an inline `<head>` script before any other JS executes

### 3.3 Script Blocking Strategy

- [ ] Non-essential `<script>` tags rendered with `type="text/plain"` and `data-consent-category`
- [ ] `script-unlocker.js` clones element with correct `type` post-consent
- [ ] External scripts re-created and re-inserted to trigger browser fetch
- [ ] `type="text/plain"` pattern used for Tag Registry proxies

### 3.4 Cookie / Storage API

- [ ] HTTP cookie (`cookieconsent_state`): primary canonical store, server-readable
- [ ] `localStorage` mirror: fast synchronous client reads without cookie parsing
- [ ] Same-session suppression: banner not shown again during a single visit if already shown
- [ ] `SameSite`, `Secure`, and configurable domain scope per TYPO3 site

### 3.5 DOM Events

```js
// Fired on grant:
window.dispatchEvent(new CustomEvent('typo3consent:granted', {
  detail: { categories: ['media', 'analytics'] }
}));

// Content elements listen:
window.addEventListener('typo3consent:granted', (e) => { ... });
```

---

## Phase 4 — Tag Registry

### 4.1 Data Model (`tx_cookieconsent_tag`)

| Field | Type | Purpose |
|---|---|---|
| `uid`, `pid`, `sys_language_uid` | Standard | TYPO3 record identity and language |
| `title` | varchar | Human label, e.g. "Google Analytics 4" |
| `identifier` | varchar | Machine key for JS lookup, e.g. `ga4` |
| `category` | FK | Consent category gating this tag |
| `trigger` | enum | `page_load` \| `consent_granted` \| `custom_event` |
| `script_content` | text | Inline script body |
| `script_src` | varchar | External script URL |
| `load_position` | enum | `head` \| `body_top` \| `body_end` |
| `sites` | MM | Scoped to specific TYPO3 site roots |
| `priority` | int | Load order within same category |
| `auto_discovered` | bool | Flagged true if created by discovery engine |

- [ ] Full TCA with inline editing, language overlay, site scoping MM relation
- [ ] `DataProcessor` to inject Tag records into page layout
- [ ] `TagRegistry` PHP service to load and serve records per site/position

### 4.2 GTM & Google Consent Mode v2

- [ ] Synchronous `gtag('consent', 'default', ...)` block in `<head>` via Fluid (server-rendered, before any other script)
- [ ] GTM loaded only after user grants relevant consent categories
- [ ] On grant: bootstrap GTM dynamically; push `consent_update` to `dataLayer` with granted category flags
- [ ] On subsequent consent changes: push further `consent_update` so GTM triggers re-evaluate
- [ ] Open topic: `wait_for_update`, `url_passthrough`, `ads_data_redaction` advanced fields

---

## Phase 5 — Content Element Integration

### 5.1 Placeholder Pattern

```html
<div class="consent-placeholder"
     data-consent-category="media"
     data-consent-ce="youtube"
     data-embed="<iframe src='https://youtube.com/embed/...'></iframe>">
  <img src="thumbnail.jpg" alt="Video preview" />
  <p>This video requires your consent to load YouTube content.</p>
  <button class="consent-placeholder__accept">Accept &amp; Watch</button>
</div>
```

- [ ] Server-side placeholder rendered by default; real embed in `data-embed`
- [ ] `placeholder.js` swaps in embed on `typo3consent:granted` or inline button click
- [ ] Inline "Accept & Watch" grants only the relevant category without opening the full modal
- [ ] `T3Consent.onGrant('media', fn)` pattern for timing-agnostic content element scripts

### 5.2 Registration Contract for Third-Party Extensions

- [ ] Define API for other extensions to register custom placeholders (open topic)
- [ ] Document PSR-14 event signatures as integration contract

---

## Phase 6 — Opt-Out & Data Subject Rights

### 6.1 Opt-Out

- [ ] Per-category and per-cookie withdrawal in the modal
- [ ] JS layer unloads / disables active scripts where technically possible on withdrawal
- [ ] Server-side: consent record marked withdrawn in audit log
- [ ] `ConsentWithdrawnEvent` fired for third-party extensions to react

### 6.2 Data Download (`DataExportService`)

- [ ] `DataDownloadController` handles download requests
- [ ] Export formats: JSON and PDF
- [ ] Record includes: timestamp, policy version hash, accepted categories, user agent
- [ ] If IP address or `fe_user` UID stored, included in download
- [ ] Authenticated FE users: optional link to full profile data

---

## Phase 7 — Automated Script Discovery

### 7.1 Layer 1 — Static Analysis (CLI Command)

```
./vendor/bin/typo3 consent:discover --site=main
```

- [ ] Crawl TypoScript setup for `page.includeJSFooter`, `page.headerData`, and similar includes
- [ ] Parse Fluid templates for `<script>` tags and known third-party `src` patterns
- [ ] Match findings against vendor fingerprint database
- [ ] Propose Tag records for editor review — never auto-activate
- [ ] `DiscoveryCLI` command class registered via `Configuration/Services.yaml`

### 7.2 Layer 2 — Runtime Discovery (Middleware)

- [ ] Dev-mode middleware intercepts rendered HTML response
- [ ] Scans for script tags not present in Tag Registry
- [ ] Writes results to discovery log
- [ ] Surfaces actionable notifications in backend module: "3 unregistered scripts detected"
- [ ] `TagDiscoveredEvent` fired for each finding

### 7.3 Layer 3 — Vendor Fingerprint Database

Distributed as a separate composer package, extensible via `TagDiscoveredEvent`.

| URL Pattern | Suggested Title | Category | Known Cookies |
|---|---|---|---|
| `googletagmanager.com/gtm.js` | Google Tag Manager | Marketing | `_ga`, `_gid` |
| `connect.facebook.net` | Meta Pixel | Marketing | `_fbp`, `fr` |
| `cdn.hotjar.com` | Hotjar | Analytics | `_hjid` |
| `youtube.com/embed` | YouTube Embed | Media | `YSC`, `VISITOR_INFO1_LIVE` |
| `maps.googleapis.com` | Google Maps | Media | `NID`, `1P_JAR` |

---

## Phase 8 — Backend Module

- [ ] Backend module: consent overview, discovery results, audit log viewer
- [ ] Editor workflow and record creation flow for Tag Registry (open topic)
- [ ] Backend preview mode for modal appearance
- [ ] Actionable notifications for unregistered scripts from runtime discovery

---

## Phase 9 — Multilingual & Multi-Domain

- [ ] All UI strings via XLF translation files following TYPO3 conventions
- [ ] Cookie and category descriptions per language using `sys_language_uid`
- [ ] RTL layout support in CSS
- [ ] Fallback chain: site language → default language → hardcoded fallback string
- [ ] Cookie domain configurable per site (e.g. `.example.com` vs `.example.de`)
- [ ] Shared consent across subdomains via domain-scoped cookies (opt-in behaviour)
- [ ] Per-site policy versioning for different legal texts per country or domain

---

## Page Request Flow (Reference)

1. **HEAD** — Consent Mode defaults injected synchronously (server-rendered Fluid, before any other script)
2. **HEAD** — `T3Consent` singleton bootstrapped from cookie state (inline script, synchronous)
3. **BODY** — Content elements render with placeholders or live embeds based on `T3Consent.has()`
4. **BODY END** — Tag Registry scripts rendered as blocked proxies (`type="text/plain"`) or live if already consented
5. **USER ACTION** — Consent granted via modal or inline "Accept & Watch" button
   - `T3Consent` updates HTTP cookie and localStorage
   - `typo3consent:granted` CustomEvent dispatched on `window`
   - `ScriptUnlocker` activates blocked Tag Registry scripts
   - GTM bootstrapped with `dataLayer` `consent_update` event
   - Google Consent Mode v2 updated via `gtag('consent', 'update', ...)`
   - Content element listeners activate players / embeds
   - `ConsentAuditLog` entry written server-side via async POST

---

## Open Topics

- [ ] Backend UI design for Tag Registry — editor workflow and record creation flow
- [ ] Full `T3Consent` JS API interface specification
- [ ] Discovery engine — middleware implementation and backend notification UX
- [ ] Integration contract for third-party TYPO3 extensions (PSR-14 event signatures)
- [ ] Placeholder component system — how other extensions register custom placeholders
- [ ] Consent Mode v2 advanced fields: `wait_for_update`, `url_passthrough`, `ads_data_redaction`
- [ ] IAB TCF 2.2 compliance layer (optional, relevant for large publishing sites)

---

## Storage Reference

| Layer | Purpose | Server-readable |
|---|---|---|
| HTTP cookie `cookieconsent_state` | Primary canonical store | Yes |
| `localStorage` mirror | Fast synchronous JS reads | No |
| `fe_users` field (optional) | Cross-device persistence for authenticated users | Yes |
| `tx_cookieconsent_log` DB table | Immutable audit log; append-only | Yes |
