.. _events:

==========
PSR-14 Events
==========

The extension dispatches five PSR-14 events that allow integrators to
modify or veto processing at key points.

Event overview
==============

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - Event class
     - Dispatched when
   * - ``BeforeBannerRenderedEvent``
     - Before the cookie banner HTML is assembled
   * - ``AfterBannerRenderedEvent``
     - After the full injection HTML string is built
   * - ``BeforeConsentStoredEvent``
     - Before user preferences are written (record endpoint)
   * - ``AfterConsentStoredEvent``
     - After user preferences have been written
   * - ``BeforeContentElementGatedEvent``
     - Before each gated content element is wrapped

Listener registration
=====================

Register event listeners in ``EXT:my_sitepackage/Configuration/Services.yaml``:

.. code-block:: yaml

   services:
     MyVendor\MySitepackage\EventListener\ModifyBanner:
       tags:
         - name: event.listener
           identifier: 'my-sitepackage/modify-banner'
           event: Maispace\MaispaceConsent\Event\BeforeBannerRenderedEvent

BeforeBannerRenderedEvent
=========================

Dispatched in ``ConsentBannerMiddleware`` before Fluid renders the banner
and modal partials.

.. code-block:: php

   use Maispace\MaispaceConsent\Event\BeforeBannerRenderedEvent;

   final class ModifyBannerVariables
   {
       public function __invoke(BeforeBannerRenderedEvent $event): void
       {
           // Add a custom variable to the Fluid templates
           $vars = $event->getVariables();
           $vars['customMessage'] = 'Hello from a listener!';
           $event->setVariables($vars);
       }
   }

Call ``$event->disable()`` to prevent the banner from being injected at all.

AfterBannerRenderedEvent
========================

Dispatched after the complete injection HTML (categories JSON + banner +
modal + script tag) has been assembled.

.. code-block:: php

   use Maispace\MaispaceConsent\Event\AfterBannerRenderedEvent;

   final class WrapInjection
   {
       public function __invoke(AfterBannerRenderedEvent $event): void
       {
           $event->setHtml('<!-- consent start -->' . $event->getHtml() . '<!-- consent end -->');
       }
   }

BeforeConsentStoredEvent
========================

Dispatched in ``ConsentRecordMiddleware`` before individual category
preferences are persisted as statistics.

.. code-block:: php

   use Maispace\MaispaceConsent\Event\BeforeConsentStoredEvent;

   final class ValidatePreferences
   {
       public function __invoke(BeforeConsentStoredEvent $event): void
       {
           $prefs = $event->getPreferences();

           // Remove unknown category UIDs
           $allowed = [1, 2, 3];
           $filtered = array_filter(
               $prefs,
               static fn ($uid) => in_array((int)$uid, $allowed, true),
               ARRAY_FILTER_USE_KEY
           );
           $event->setPreferences($filtered);
       }
   }

Call ``$event->cancel()`` to abort storing preferences entirely.

AfterConsentStoredEvent
=======================

Dispatched after all category preferences have been recorded.
The event provides read-only access to the stored preferences map.

.. code-block:: php

   use Maispace\MaispaceConsent\Event\AfterConsentStoredEvent;

   final class TriggerAnalyticsOptIn
   {
       public function __invoke(AfterConsentStoredEvent $event): void
       {
           $prefs = $event->getPreferences();
           // React to stored preferences (e.g. trigger server-side analytics)
       }
   }

BeforeContentElementGatedEvent
================================

Dispatched in ``ConsentGatingProcessor`` once per gated content element.

.. code-block:: php

   use Maispace\MaispaceConsent\Event\BeforeContentElementGatedEvent;

   final class SkipGatingForAdmins
   {
       public function __invoke(BeforeContentElementGatedEvent $event): void
       {
           // Skip gating for a specific element
           if ($event->getContentElementUid() === 42) {
               $event->skip();
               return;
           }

           // Replace category requirement
           $event->setCategoryUids([5]);
       }
   }
