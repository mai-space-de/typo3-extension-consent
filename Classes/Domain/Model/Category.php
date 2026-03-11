<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Domain\Model;

class Category
{
    private int $uid = 0;
    private int $pid = 0;
    private string $name = '';
    private string $description = '';
    private bool $isEssential = false;
    private int $sorting = 0;

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $category = new self();

        $uid = $row['uid'] ?? null;
        $category->uid = is_int($uid) ? $uid : (int)(is_string($uid) ? $uid : 0);

        $pid = $row['pid'] ?? null;
        $category->pid = is_int($pid) ? $pid : (int)(is_string($pid) ? $pid : 0);

        $name = $row['name'] ?? null;
        $category->name = is_string($name) ? $name : '';

        $description = $row['description'] ?? null;
        $category->description = is_string($description) ? $description : '';

        $category->isEssential = (bool)($row['is_essential'] ?? false);

        $sorting = $row['sorting'] ?? null;
        $category->sorting = is_int($sorting) ? $sorting : (int)(is_string($sorting) ? $sorting : 0);

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
