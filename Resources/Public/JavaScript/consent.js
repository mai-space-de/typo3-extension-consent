/**
 * Maispace Consent — frontend runtime
 *
 * Reads consent cookie, shows/hides the banner, drives the modal,
 * applies content-element gating and POSTs statistics to the record endpoint.
 */
(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Bootstrap — locate the injected script element to read configuration
    // -------------------------------------------------------------------------
    const scriptEl = document.getElementById('maispace-consent-script');
    if (!scriptEl) {
        return;
    }

    const cookieName = scriptEl.dataset.cookieName || 'maispace_consent';
    const cookieLifetime = parseInt(scriptEl.dataset.cookieLifetime || '365', 10);
    const recordEndpoint = scriptEl.dataset.recordEndpoint || '/maispace/consent/record';

    // Read category definitions from the embedded JSON element
    const categoriesEl = document.getElementById('maispace-consent-categories');
    if (!categoriesEl) {
        return;
    }

    let categories = [];
    try {
        categories = JSON.parse(categoriesEl.textContent || '[]');
    } catch (e) {
        return;
    }

    if (!Array.isArray(categories) || categories.length === 0) {
        return;
    }

    // -------------------------------------------------------------------------
    // Cookie helpers
    // -------------------------------------------------------------------------

    /**
     * @param {string} name
     * @returns {string|null}
     */
    function getCookie(name) {
        const match = document.cookie.match(
            new RegExp('(?:^|;)\\s*' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)')
        );
        return match ? decodeURIComponent(match[1]) : null;
    }

    /**
     * @param {string} name
     * @param {string} value
     * @param {number} days
     */
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setDate(expires.getDate() + days);
        let cookie =
            name + '=' + encodeURIComponent(value) +
            '; expires=' + expires.toUTCString() +
            '; path=/; SameSite=Lax';
        if (location.protocol === 'https:') {
            cookie += '; Secure';
        }
        document.cookie = cookie;
    }

    // -------------------------------------------------------------------------
    // Preference helpers
    // -------------------------------------------------------------------------

    /**
     * @returns {Object.<string, boolean>}
     */
    function getPreferences() {
        const raw = getCookie(cookieName);
        if (!raw) {
            return {};
        }
        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                return parsed;
            }
        } catch (e) {
            // fall through
        }
        return {};
    }

    /**
     * Returns true when every non-essential category has an explicit decision.
     *
     * @param {Object.<string, boolean>} prefs
     * @returns {boolean}
     */
    function areAllDecided(prefs) {
        return categories
            .filter(function (c) { return !c.isEssential; })
            .every(function (c) { return Object.prototype.hasOwnProperty.call(prefs, String(c.uid)); });
    }

    // -------------------------------------------------------------------------
    // Content-element gating
    // -------------------------------------------------------------------------

    /**
     * Show or hide gated content elements based on current preferences.
     *
     * @param {Object.<string, boolean>} prefs
     */
    function applyGating(prefs) {
        document.querySelectorAll('[data-maispace-consent-required]').forEach(function (el) {
            const raw = el.getAttribute('data-maispace-consent-required') || '';
            const required = raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean);

            if (required.length === 0) {
                el.removeAttribute('hidden');
                return;
            }

            const granted = required.every(function (uid) { return prefs[uid] === true; });
            if (granted) {
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', '');
            }
        });

        // Show placeholders whose associated gated element is still hidden
        document.querySelectorAll('[data-maispace-consent-placeholder]').forEach(function (placeholder) {
            const targetUid = placeholder.getAttribute('data-maispace-consent-placeholder') || '';
            const gated = document.querySelector('[data-maispace-consent-uid="' + targetUid + '"]');
            if (gated) {
                if (gated.hasAttribute('hidden')) {
                    placeholder.removeAttribute('hidden');
                } else {
                    placeholder.setAttribute('hidden', '');
                }
            }
        });
    }

    // -------------------------------------------------------------------------
    // Banner
    // -------------------------------------------------------------------------

    function showBanner() {
        const banner = document.getElementById('maispace-consent-banner');
        if (banner) {
            banner.removeAttribute('hidden');
        }
    }

    function hideBanner() {
        const banner = document.getElementById('maispace-consent-banner');
        if (banner) {
            banner.setAttribute('hidden', '');
        }
    }

    // -------------------------------------------------------------------------
    // Modal
    // -------------------------------------------------------------------------

    /** @type {HTMLElement|null} */
    let lastFocusBeforeModal = null;

    function openModal() {
        const modal = document.getElementById('maispace-consent-modal');
        if (!modal) {
            return;
        }

        const prefs = getPreferences();

        // Sync checkbox states to current preferences
        modal.querySelectorAll('[data-maispace-consent-category]').forEach(function (checkbox) {
            if (checkbox.disabled) {
                return;
            }
            const uid = checkbox.getAttribute('data-maispace-consent-category');
            checkbox.checked = prefs[String(uid)] === true;
        });

        lastFocusBeforeModal = document.activeElement;
        modal.removeAttribute('hidden');

        // Move focus into the modal
        const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) {
            firstFocusable.focus();
        }
    }

    function closeModal() {
        const modal = document.getElementById('maispace-consent-modal');
        if (modal) {
            modal.setAttribute('hidden', '');
        }
        // Restore focus
        if (lastFocusBeforeModal && typeof lastFocusBeforeModal.focus === 'function') {
            lastFocusBeforeModal.focus();
        }
        lastFocusBeforeModal = null;
    }

    // Trap focus inside the modal
    document.addEventListener('keydown', function (e) {
        const modal = document.getElementById('maispace-consent-modal');
        if (!modal || modal.hasAttribute('hidden')) {
            return;
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal();
            return;
        }

        if (e.key !== 'Tab') {
            return;
        }

        const focusable = Array.from(
            modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
        );

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    // -------------------------------------------------------------------------
    // Saving and recording
    // -------------------------------------------------------------------------

    /**
     * @param {Object.<string, boolean>} prefs
     */
    function savePreferences(prefs) {
        setCookie(cookieName, JSON.stringify(prefs), cookieLifetime);

        // POST statistics to record endpoint (fire-and-forget)
        try {
            fetch(recordEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ preferences: prefs }),
            }).catch(function () { /* ignore network errors */ });
        } catch (e) { /* ignore */ }

        applyGating(prefs);
        hideBanner();
        closeModal();
    }

    // -------------------------------------------------------------------------
    // Action handlers
    // -------------------------------------------------------------------------

    /**
     * @param {string} action
     */
    function handleAction(action) {
        if (action === 'accept-all') {
            const prefs = {};
            categories.forEach(function (c) {
                prefs[String(c.uid)] = true;
            });
            savePreferences(prefs);
        } else if (action === 'reject-all') {
            const prefs = {};
            categories.forEach(function (c) {
                prefs[String(c.uid)] = Boolean(c.isEssential);
            });
            savePreferences(prefs);
        } else if (action === 'open-modal') {
            openModal();
        } else if (action === 'close-modal') {
            closeModal();
        } else if (action === 'save-preferences') {
            const modal = document.getElementById('maispace-consent-modal');
            const prefs = {};

            if (modal) {
                modal.querySelectorAll('[data-maispace-consent-category]').forEach(function (checkbox) {
                    const uid = checkbox.getAttribute('data-maispace-consent-category');
                    prefs[String(uid)] = checkbox.disabled ? true : checkbox.checked;
                });
            }

            savePreferences(prefs);
        }
    }

    // -------------------------------------------------------------------------
    // Event delegation — clicks on action elements
    // -------------------------------------------------------------------------
    document.addEventListener('click', function (e) {
        const target = e.target.closest('[data-maispace-consent-action]');
        if (!target) {
            return;
        }
        e.preventDefault();
        handleAction(target.getAttribute('data-maispace-consent-action'));
    });

    // -------------------------------------------------------------------------
    // Initialise on DOMContentLoaded (or immediately if already loaded)
    // -------------------------------------------------------------------------
    function init() {
        const prefs = getPreferences();
        applyGating(prefs);

        if (!areAllDecided(prefs)) {
            showBanner();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
