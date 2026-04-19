/**
 * Mai Consent — GDPR consent banner + modal + content gate.
 *
 * Cookie name: mai_consent
 * Cookie value: JSON — { categories: { <identifier>: true|false, ... }, ts: <timestamp> }
 *
 * Public API on window.MaiConsent:
 *   hasConsent(identifier)  — true if user accepted category
 *   getAll()                — full consent object or null
 */
(function () {
  'use strict';

  const COOKIE_NAME = 'mai_consent';
  const COOKIE_DAYS = 365;
  const API_PATH = '/api/consent';

  function readCookie() {
    const match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_NAME + '=([^;]*)'));
    if (!match) return null;
    try { return JSON.parse(decodeURIComponent(match[1])); } catch { return null; }
  }

  function writeCookie(data) {
    const exp = new Date(Date.now() + COOKIE_DAYS * 864e5).toUTCString();
    document.cookie = COOKIE_NAME + '=' + encodeURIComponent(JSON.stringify(data)) + '; expires=' + exp + '; path=/; SameSite=Lax';
  }

  function getStoragePid() {
    const el = document.getElementById('mai-consent-banner');
    return el ? parseInt(el.dataset.storagePid || '0', 10) : 0;
  }

  function persistToServer(consents) {
    const entries = Object.entries(consents).map(([identifier, accepted]) => ({ identifier, accepted }));
    navigator.sendBeacon(API_PATH, new Blob(
      [JSON.stringify({ consents: entries, storagePid: getStoragePid() })],
      { type: 'application/json' }
    ));
  }

  function applyConsent(categories) {
    writeCookie({ categories, ts: Date.now() });
    persistToServer(categories);
    updateGates(categories);
    hideBanner();
  }

  function updateGates(categories) {
    document.querySelectorAll('.mai-consent-gate').forEach(function (gate) {
      const id = gate.dataset.consentCategory;
      const granted = categories[id] === true;
      gate.dataset.consentState = granted ? 'granted' : 'denied';
      const content = gate.querySelector('.mai-consent-gate__content');
      const placeholder = gate.querySelector('.mai-consent-gate__placeholder');
      if (content) content.hidden = !granted;
      if (placeholder) placeholder.hidden = granted;
    });
  }

  function showBanner() {
    const banner = document.getElementById('mai-consent-banner');
    if (banner) banner.hidden = false;
  }

  function hideBanner() {
    const banner = document.getElementById('mai-consent-banner');
    if (banner) banner.hidden = true;
    const modal = document.getElementById('mai-consent-modal');
    if (modal) modal.hidden = true;
  }

  function openModal() {
    const modal = document.getElementById('mai-consent-modal');
    if (modal) modal.hidden = false;
  }

  function collectFormState() {
    const categories = {};
    document.querySelectorAll('[data-consent-identifier]').forEach(function (el) {
      categories[el.dataset.consentIdentifier] = el.checked;
    });
    return categories;
  }

  function init() {
    const existing = readCookie();
    if (existing && existing.categories) {
      updateGates(existing.categories);
      return;
    }
    showBanner();
  }

  document.addEventListener('DOMContentLoaded', function () {
    init();

    document.addEventListener('click', function (e) {
      const action = e.target.closest('[data-consent-action]')?.dataset.consentAction;
      if (!action) return;

      if (action === 'acceptAll') {
        const categories = {};
        document.querySelectorAll('[data-consent-identifier]').forEach(function (el) {
          categories[el.dataset.consentIdentifier] = true;
        });
        applyConsent(categories);
      } else if (action === 'rejectAll') {
        const categories = {};
        document.querySelectorAll('[data-consent-identifier]').forEach(function (el) {
          categories[el.dataset.consentIdentifier] = false;
        });
        applyConsent(categories);
      } else if (action === 'openModal') {
        openModal();
      } else if (action === 'closeModal') {
        const modal = document.getElementById('mai-consent-modal');
        if (modal) modal.hidden = true;
      }
    });

    const form = document.getElementById('mai-consent-form');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        applyConsent(collectFormState());
      });
    }
  });

  window.MaiConsent = {
    hasConsent: function (identifier) {
      const data = readCookie();
      return !!(data && data.categories && data.categories[identifier] === true);
    },
    getAll: function () {
      return readCookie();
    },
  };
})();
