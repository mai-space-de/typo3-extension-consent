<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Event;

final class AfterBannerRenderedEvent
{
    public function __construct(
        private string $html,
    ) {
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
    }
}
