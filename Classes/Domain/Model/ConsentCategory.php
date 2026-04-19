<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class ConsentCategory extends AbstractEntity
{
    protected string $title = '';
    protected string $identifier = '';
    protected string $description = '';
    protected bool $isRequired = false;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): void
    {
        $this->isRequired = $isRequired;
    }
}
