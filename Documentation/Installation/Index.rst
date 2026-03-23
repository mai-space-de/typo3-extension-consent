.. _installation:

============
Installation
============

Requirements
============

* TYPO3 13.4 LTS
* PHP 8.2 or later
* No additional PHP extensions required

Composer installation
=====================

.. code-block:: bash

   composer require maispace/mai-consent

TYPO3 will automatically discover the extension. No manual activation is
required.

TypoScript inclusion
====================

Include the extension's TypoScript in your site package's setup file:

.. code-block:: typoscript

   @import 'EXT:mai_consent/Configuration/TypoScript/setup.typoscript'

This registers the ``lib.contentElement`` data processor and all plugin
settings with their defaults.

Database update
===============

After installation run the TYPO3 Database Analyser in the Admin Tools to
create the two tables the extension requires:

* ``tx_mai_consent_category`` — consent categories
* ``tx_mai_consent_statistic`` — anonymised consent event log

No further configuration is required for basic operation. The cookie banner
and record middleware are registered automatically.

First steps
===========

1. Navigate to **Web → Consent** in the TYPO3 backend.
2. Create at least one category (e.g. *Essential*).  Mark it as essential
   so that users cannot decline it.
3. Visit the frontend — the cookie banner will appear on first load.
