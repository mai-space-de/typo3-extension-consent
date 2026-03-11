<?php

declare(strict_types=1);

namespace Maispace\MaispaceConsent\Event;

final class BeforeContentElementGatedEvent
{
    private bool $skip;

    public function __construct(
        private readonly int $contentElementUid,
        private array $categoryUids,
        bool $skip = false,
    ) {
        $this->skip = $skip;
    }

    public function getContentElementUid(): int
    {
        return $this->contentElementUid;
    }

    public function getCategoryUids(): array
    {
        return $this->categoryUids;
    }

    public function setCategoryUids(array $categoryUids): void
    {
        $this->categoryUids = $categoryUids;
    }

    public function shouldSkip(): bool
    {
        return $this->skip;
    }

    public function skip(): void
    {
        $this->skip = true;
    }
}
