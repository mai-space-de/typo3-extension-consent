<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Service;

use Maispace\MaispaceConsent\Domain\Model\Category;
use Maispace\MaispaceConsent\Domain\Repository\CategoryRepository;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * @return Category[]
     */
    public function getAllCategories(): array
    {
        return $this->categoryRepository->findAll();
    }

    /**
     * @return Category[]
     */
    public function getEssentialCategories(): array
    {
        return array_filter(
            $this->categoryRepository->findAll(),
            static fn (Category $category) => $category->isEssential()
        );
    }

    public function getCategoryByUid(int $uid): ?Category
    {
        return $this->categoryRepository->findByUid($uid);
    }

    public function createCategory(string $name, string $description, bool $isEssential): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setDescription($description);
        $category->setIsEssential($isEssential);

        $this->categoryRepository->insert($category);

        return $category;
    }

    public function updateCategory(int $uid, string $name, string $description, bool $isEssential): void
    {
        $category = $this->categoryRepository->findByUid($uid);

        if ($category === null) {
            return;
        }

        $category->setName($name);
        $category->setDescription($description);
        $category->setIsEssential($isEssential);

        $this->categoryRepository->update($category);
    }

    public function deleteCategory(int $uid): void
    {
        $this->categoryRepository->delete($uid);
    }

    /**
     * @param int[] $sortedUids UIDs in the desired display order
     */
    public function reorderCategories(array $sortedUids): void
    {
        $this->categoryRepository->updateSorting($sortedUids);
    }
}
