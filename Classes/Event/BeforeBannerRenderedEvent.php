<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Event;

final class BeforeBannerRenderedEvent
{
    private bool $enabled;

    public function __construct(
        private array $variables,
        bool $enabled = true,
    ) {
        $this->enabled = $enabled;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }
}
