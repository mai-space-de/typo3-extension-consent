<?php

declare(strict_types=1);

namespace Maispace\MaispaceConsent\Domain\Model;

class Category
{
    private int $uid = 0;
    private int $pid = 0;
    private string $name = '';
    private string $description = '';
    private bool $isEssential = false;
    private int $sorting = 0;

    public static function fromRow(array $row): self
    {
        $category = new self();
        $category->uid = (int)($row['uid'] ?? 0);
        $category->pid = (int)($row['pid'] ?? 0);
        $category->name = (string)($row['name'] ?? '');
        $category->description = (string)($row['description'] ?? '');
        $category->isEssential = (bool)($row['is_essential'] ?? false);
        $category->sorting = (int)($row['sorting'] ?? 0);

        return $category;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): void
    {
        $this->pid = $pid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isEssential(): bool
    {
        return $this->isEssential;
    }

    public function setIsEssential(bool $isEssential): void
    {
        $this->isEssential = $isEssential;
    }

    public function getSorting(): int
    {
        return $this->sorting;
    }

    public function setSorting(int $sorting): void
    {
        $this->sorting = $sorting;
    }
}
