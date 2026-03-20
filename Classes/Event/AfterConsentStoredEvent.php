<?php

declare(strict_types = 1);

namespace Maispace\MaiConsent\Event;

final class AfterConsentStoredEvent
{
    /**
     * @param array<int|string, bool> $preferences
     */
    public function __construct(
        private readonly array $preferences,
    ) {
    }

    /**
     * @return array<int|string, bool>
     */
    public function getPreferences(): array
    {
        return $this->preferences;
    }
}
