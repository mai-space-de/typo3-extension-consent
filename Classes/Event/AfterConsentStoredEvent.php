<?php

declare(strict_types=1);

namespace Maispace\MaispaceConsent\Event;

final class AfterConsentStoredEvent
{
    public function __construct(
        private readonly array $preferences,
    ) {}

    public function getPreferences(): array
    {
        return $this->preferences;
    }
}
