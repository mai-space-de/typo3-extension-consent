<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class ConsentLog extends AbstractEntity
{
    protected ?ConsentCategory $category = null;
    protected bool $accepted = false;
    protected string $session = '';
    protected string $ipAddress = '';

    public function getCategory(): ?ConsentCategory
    {
        return $this->category;
    }

    public function setCategory(?ConsentCategory $category): void
    {
        $this->category = $category;
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function setAccepted(bool $accepted): void
    {
        $this->accepted = $accepted;
    }

    public function getSession(): string
    {
        return $this->session;
    }

    public function setSession(string $session): void
    {
        $this->session = $session;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }
}
