.. _development:

===========
Development
===========

Running tests
=============

.. code-block:: bash

   composer install
   composer test

Or with the ``--testdox`` reporter:

.. code-block:: bash

   vendor/bin/phpunit --configuration phpunit.xml.dist --testdox

Test structure
==============

All tests are pure unit tests — no database and no TYPO3 installation are
required.

.. list-table::
   :header-rows: 1
   :widths: 50 50

   * - File
     - What it tests
   * - ``Tests/Unit/Service/ConsentCookieServiceTest.php``
     - Cookie parsing, preference read/write, essential category handling
   * - ``Tests/Unit/Service/CategoryServiceTest.php``
     - Category CRUD, ordering, essential flag
   * - ``Tests/Unit/Middleware/ConsentBannerMiddlewareTest.php``
     - Banner injection, CSS/JS injection, suppression conditions
   * - ``Tests/Unit/DataProcessing/ConsentGatingProcessorTest.php``
     - Element gating, skip event, category UID override

Running linters
===============

.. code-block:: bash

   # Run all lint checks
   composer lint:check

   # Auto-fix code style
   composer lint:fix

Individual checks:

.. code-block:: bash

   # PHP-CS-Fixer
   composer check:phpcs

   # PHPStan (level max)
   composer check:phpstan

   # TypoScript lint
   composer check:typoscript

   # EditorConfig
   composer check:editorconfig

CI pipeline
===========

The GitHub Actions workflow (``.github/workflows/ci.yml``) runs on every
push and pull request:

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Job
     - What it checks
   * - ``composer-validate``
     - Validates ``composer.json``
   * - ``unit-tests``
     - PHPUnit across PHP 8.2 / 8.3 × TYPO3 13.4
   * - ``static-analysis``
     - PHPStan (``phpstan.neon``, level max)
   * - ``code-style``
     - EditorConfig + PHP-CS-Fixer
   * - ``typoscript-lint``
     - TypoScript style/structure

Adding a custom JavaScript
==========================

Frontend assets (CSS and JavaScript) are registered via the ``<mai:css>``
and ``<mai:js>`` ViewHelpers from the ``maispace/assets`` package.  The
extension ships
``EXT:maispace_consent/Resources/Private/Templates/Frontend/Assets.html``
which is rendered through a ``page.8 = FLUIDTEMPLATE`` TypoScript object.

Runtime configuration is passed from ``ConsentBannerMiddleware`` via an
inline JSON element rather than ``data-*`` attributes, so the script can
be registered through the AssetCollector without any custom script tag:

.. code-block:: html

   <script type="application/json" id="maispace-consent-config">
     {"cookieName":"maispace_consent","cookieLifetime":365,"recordEndpoint":"/maispace/consent/record"}
   </script>

Cookie structure
================

Preferences are stored as a JSON object keyed by category UID:

.. code-block:: json

   { "1": true, "2": false, "3": true }

``true`` means the user accepted the category; ``false`` means rejected.
Essential categories are always ``true`` in the stored value.

The cookie is written entirely client-side.  No preference data is sent to
third parties.  The ``/maispace/consent/record`` endpoint receives only a
boolean map of category decisions (no IP address, no personal data) and
stores aggregated counts for the backend statistics view.
