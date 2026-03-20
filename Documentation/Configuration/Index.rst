.. _configuration:

=============
Configuration
=============

All settings live under the ``plugin.tx_mai_consent`` TypoScript
namespace.

.. code-block:: typoscript

   plugin.tx_mai_consent {
       cookie {
           name = mai_consent      # cookie name
           lifetime = 365               # days until the cookie expires
           sameSite = Lax               # Strict | Lax | None
       }
       banner {
           enable = 1                   # 0 to suppress banner globally (e.g. in dev)
           position = bottom            # bottom | top | bottom-left | bottom-right
           showOnEveryPage = 0          # 1 = re-show banner on every page
       }
       modal {
           showCategoryDescriptions = 1 # 0 to hide descriptions in the modal
       }
       gating {
           placeholder = 0              # 1 = render a placeholder for hidden elements
           placeholderPartial = Consent/Placeholder
       }
       statistics {
           enable = 1                   # 0 to disable consent event recording
           retentionDays = 90           # delete events older than N days (0 = keep forever)
       }
       view {
           templateRootPaths.0 = EXT:mai_consent/Resources/Private/Templates/
           partialRootPaths.0  = EXT:mai_consent/Resources/Private/Partials/
           layoutRootPaths.0   = EXT:mai_consent/Resources/Private/Layouts/
       }
   }

Reference
=========

.. confval-menu::
   :display: table
   :type:
   :default:

.. confval:: cookie.name
   :type: string
   :default: mai_consent

   Name of the first-party cookie that stores user preferences.

.. confval:: cookie.lifetime
   :type: integer
   :default: 365

   Cookie lifetime in days.

.. confval:: cookie.sameSite
   :type: string
   :default: Lax

   ``SameSite`` attribute value.  One of ``Strict``, ``Lax`` or ``None``.

.. confval:: banner.enable
   :type: boolean
   :default: 1

   Set to ``0`` to suppress the banner entirely (useful in development or
   when using a custom consent UI).

.. confval:: banner.position
   :type: string
   :default: bottom

   Controls the CSS modifier class applied to the banner container.
   Available values: ``bottom``, ``top``, ``bottom-left``, ``bottom-right``.

.. confval:: banner.showOnEveryPage
   :type: boolean
   :default: 0

   When set to ``1`` the banner is shown on every page load if the stored
   preferences do not cover all active non-essential categories.

.. confval:: modal.showCategoryDescriptions
   :type: boolean
   :default: 1

   Show the category description text inside the consent modal.

.. confval:: gating.placeholder
   :type: boolean
   :default: 0

   When set to ``1`` a placeholder element is rendered alongside hidden
   gated content.

.. confval:: gating.placeholderPartial
   :type: string
   :default: Consent/Placeholder

   Partial template used for the placeholder.  Override via
   ``partialRootPaths`` to provide a custom placeholder.

.. confval:: statistics.enable
   :type: boolean
   :default: 1

   Set to ``0`` to disable recording of consent events entirely.

.. confval:: statistics.retentionDays
   :type: integer
   :default: 90

   Number of days consent events are retained.  Set to ``0`` to keep all
   events indefinitely.

Template overrides
==================

Override any Fluid template by adding a higher-priority path:

.. code-block:: typoscript

   plugin.tx_mai_consent.view {
       partialRootPaths.10 = EXT:my_sitepackage/Resources/Private/Partials/
   }

Place your custom ``Banner.html``, ``Modal.html`` or ``Placeholder.html``
under that path to replace the default templates.

CSS and JavaScript
==================

Frontend assets are registered with TYPO3's **AssetCollector** via the
`<mai:css>` and `<mai:js>` ViewHelpers from the `maispace/assets` package.
The extension ships a dedicated Fluid template that is rendered as a TypoScript
``FLUIDTEMPLATE`` object during normal TYPO3 page rendering:

.. code-block:: typoscript

   page.8 = FLUIDTEMPLATE
   page.8 {
       file = EXT:mai_consent/Resources/Private/Templates/Frontend/Assets.html
   }

This ensures the stylesheet is placed in ``<head>`` and the deferred script in
the footer — both with proper caching — before the
``ConsentBannerMiddleware`` injects the banner/modal HTML.

Override the default stylesheet or script by registering your own TypoScript
``page.includeCSS`` / ``page.includeJSFooter`` entries at a higher priority,
or by wrapping the ``page.8`` object with a condition.

Internationalisation
====================

All visible frontend texts are defined in
``EXT:mai_consent/Resources/Private/Language/locallang_fe.xlf``.
Override individual labels by providing a language override file in your
site configuration.

Available translation keys:

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - Key
     - Default (English)
   * - ``banner.title``
     - Cookie Preferences
   * - ``banner.description``
     - We use cookies to improve your experience …
   * - ``banner.acceptAll``
     - Accept all
   * - ``banner.rejectAll``
     - Reject all
   * - ``banner.customize``
     - Customize
   * - ``modal.title``
     - Manage Cookie Preferences
   * - ``modal.intro``
     - Select which categories …
   * - ``modal.close``
     - Close
   * - ``modal.savePreferences``
     - Save preferences
   * - ``modal.acceptAll``
     - Accept all
   * - ``modal.rejectAll``
     - Reject all
   * - ``modal.essential``
     - Essential
   * - ``placeholder.message``
     - This content requires your consent to be displayed.
   * - ``placeholder.openModal``
     - Manage cookie preferences
