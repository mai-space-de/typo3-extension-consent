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

The extension ships ``Resources/Public/JavaScript/consent.js`` which is
loaded as a plain ``<script defer>`` tag.  The script element carries the
configuration in ``data-*`` attributes:

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - Attribute
     - Default value
   * - ``data-cookie-name``
     - ``maispace_consent``
   * - ``data-cookie-lifetime``
     - ``365``
   * - ``data-record-endpoint``
     - ``/maispace/consent/record``

The script is identified by ``id="maispace-consent-script"`` so the runtime
can read these attributes even inside a deferred script.

To replace the runtime entirely, override ``BannerRenderer::getJsPath()``
via Dependency Injection decoration.

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
