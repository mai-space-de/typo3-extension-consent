.. _content-gating:

===============
Content Gating
===============

The content-gating feature allows editors to link any content element to one
or more consent categories.  If a visitor has not granted consent for at least
one of those categories, the element is hidden until consent is given — all
without a page reload.

How it works
============

1. A TYPO3 **DataProcessor** (``ConsentGatingProcessor``) runs on every
   content element during page rendering.
2. It reads the ``tx_mai_consent_categories`` field that the extension
   adds to ``tt_content``.
3. If categories are assigned, the layout template wraps the element in a
   ``<div>`` with the ``data-maispace-consent-required`` attribute set to the
   comma-separated category UIDs and ``hidden`` as the initial state.
4. On page load the JavaScript runtime reads the stored cookie and removes
   the ``hidden`` attribute from every element whose required categories have
   been accepted.

Rendered markup
===============

A content element with category UID 2 assigned will be rendered as:

.. code-block:: html

   <div data-maispace-consent-required="2"
        data-maispace-consent-uid="<element-uid>"
        hidden="hidden">
       <!-- original content element markup -->
   </div>

Multiple categories are listed as a comma-separated value:

.. code-block:: html

   <div data-maispace-consent-required="2,3" …>

Assigning categories in the backend
=====================================

1. Open a content element in the TYPO3 backend.
2. Switch to the **Consent** tab.
3. Select one or more categories from the multi-select list.
4. Save.

Elements with no category assigned are always rendered regardless of consent.

Placeholder
===========

Enable the placeholder to show a visible hint instead of a blank space:

.. code-block:: typoscript

   plugin.tx_mai_consent.gating {
       placeholder = 1
       placeholderPartial = Consent/Placeholder
   }

When enabled, the layout template renders the ``Placeholder.html`` partial
next to the hidden element.  The placeholder contains a button that opens the
consent modal, letting visitors grant consent directly from the content area.

Override the partial via ``partialRootPaths`` to provide a custom design.

PSR-14 event
============

Before a content element is wrapped, the
``BeforeContentElementGatedEvent`` is dispatched.  Listeners can:

* inspect the content element UID and its resolved category UIDs;
* replace the category UIDs with a different set;
* call ``skip()`` to bypass gating entirely for that element.

.. code-block:: php

   use Maispace\MaiConsent\Event\BeforeContentElementGatedEvent;

   final class MyGatingListener
   {
       public function __invoke(BeforeContentElementGatedEvent $event): void
       {
           // Always show content element UID 42 regardless of categories
           if ($event->getContentElementUid() === 42) {
               $event->skip();
           }
       }
   }

Register in ``Configuration/Services.yaml``:

.. code-block:: yaml

   services:
     MyVendor\MySitepackage\EventListener\MyGatingListener:
       tags:
         - name: event.listener
           identifier: 'my-sitepackage/gating'
           event: Maispace\MaiConsent\Event\BeforeContentElementGatedEvent
