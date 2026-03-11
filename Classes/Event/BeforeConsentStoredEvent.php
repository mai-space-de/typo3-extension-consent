<?php

declare(strict_types=1);

namespace Maispace\MaispaceConsent\Event;

final class BeforeConsentStoredEvent
{
    private bool $cancelled;

    public function __construct(
        private array $preferences,
        bool $cancelled = false,
    ) {
        $this->cancelled = $cancelled;
    }

    public function getPreferences(): array
    {
        return $this->preferences;
    }

    public function setPreferences(array $preferences): void
    {
        $this->preferences = $preferences;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }
}
