<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\ViewHelper;

/**
 * Fluid ViewHelper alias for <consent:gate>.
 *
 * Delegates to ContentGateViewHelper to provide the `gate` tag name
 * for consent-gated content sections (maps, videos, etc.).
 *
 * Usage in Fluid templates:
 *   <consent:gate category="maps" placeholder="Accept maps to view this content.">
 *     ...
 *   </consent:gate>
 */
class GateViewHelper extends ContentGateViewHelper {}
