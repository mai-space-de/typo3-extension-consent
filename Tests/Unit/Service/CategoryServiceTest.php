<?php

declare(strict_types=1);

namespace Maispace\MaispaceConsent\Tests\Unit\Service;

use Maispace\MaispaceConsent\Domain\Model\Category;
use Maispace\MaispaceConsent\Domain\Repository\CategoryRepository;
use Maispace\MaispaceConsent\Service\CategoryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryService::class)]
final class CategoryServiceTest extends TestCase
{
    private CategoryRepository&MockObject $categoryRepository;
    private CategoryService $subject;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->subject = new CategoryService($this->categoryRepository);
    }

    private function buildCategory(int $uid, string $name, bool $isEssential): Category
    {
        return Category::fromRow([
            'uid'          => $uid,
            'pid'          => 0,
            'name'         => $name,
            'description'  => '',
            'is_essential' => $isEssential ? 1 : 0,
            'sorting'      => 0,
        ]);
    }

    #[Test]
    public function getAllCategoriesReturnsAllCategories(): void
    {
        $categories = [
            $this->buildCategory(1, 'Analytics', false),
            $this->buildCategory(2, 'Essential', true),
            $this->buildCategory(3, 'Marketing', false),
        ];

        $this->categoryRepository
            ->expects(self::once())
            ->method('findAll')
            ->willReturn($categories);

        $result = $this->subject->getAllCategories();

        self::assertCount(3, $result);
        self::assertSame($categories, $result);
    }

    #[Test]
    public function getAllCategoriesReturnsEmptyArrayWhenNoCategories(): void
    {
        $this->categoryRepository
            ->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->subject->getAllCategories();

        self::assertSame([], $result);
    }

    #[Test]
    public function getEssentialCategoriesReturnsOnlyEssentialCategories(): void
    {
        $categories = [
            $this->buildCategory(1, 'Analytics', false),
            $this->buildCategory(2, 'Essential', true),
            $this->buildCategory(3, 'Marketing', false),
            $this->buildCategory(4, 'Functional', true),
        ];

        $this->categoryRepository
            ->expects(self::once())
            ->method('findAll')
            ->willReturn($categories);

        $result = array_values($this->subject->getEssentialCategories());

        self::assertCount(2, $result);
        self::assertTrue($result[0]->isEssential());
        self::assertTrue($result[1]->isEssential());
        self::assertSame('Essential', $result[0]->getName());
        self::assertSame('Functional', $result[1]->getName());
    }

    #[Test]
    public function getEssentialCategoriesReturnsEmptyArrayWhenNoEssentialCategories(): void
    {
        $categories = [
            $this->buildCategory(1, 'Analytics', false),
            $this->buildCategory(3, 'Marketing', false),
        ];

        $this->categoryRepository
            ->expects(self::once())
            ->method('findAll')
            ->willReturn($categories);

        $result = $this->subject->getEssentialCategories();

        self::assertSame([], array_values($result));
    }

    #[Test]
    public function getCategoryByUidDelegatesToRepository(): void
    {
        $category = $this->buildCategory(42, 'Test', false);

        $this->categoryRepository
            ->expects(self::once())
            ->method('findByUid')
            ->with(42)
            ->willReturn($category);

        $result = $this->subject->getCategoryByUid(42);

        self::assertSame($category, $result);
    }

    #[Test]
    public function getCategoryByUidReturnsNullWhenNotFound(): void
    {
        $this->categoryRepository
            ->expects(self::once())
            ->method('findByUid')
            ->with(999)
            ->willReturn(null);

        $result = $this->subject->getCategoryByUid(999);

        self::assertNull($result);
    }

    #[Test]
    public function createCategoryInsertsAndReturnsCategory(): void
    {
        $this->categoryRepository
            ->expects(self::once())
            ->method('insert')
            ->with(self::callback(static function (Category $category) {
                return $category->getName() === 'Analytics'
                    && $category->getDescription() === 'Track page views'
                    && $category->isEssential() === false;
            }));

        $result = $this->subject->createCategory('Analytics', 'Track page views', false);

        self::assertSame('Analytics', $result->getName());
        self::assertFalse($result->isEssential());
    }

    #[Test]
    public function deleteCategoryDelegatesToRepository(): void
    {
        $this->categoryRepository
            ->expects(self::once())
            ->method('delete')
            ->with(7);

        $this->subject->deleteCategory(7);
    }

    #[Test]
    public function updateCategoryUpdatesExistingCategory(): void
    {
        $category = $this->buildCategory(5, 'Old Name', false);

        $this->categoryRepository
            ->expects(self::once())
            ->method('findByUid')
            ->with(5)
            ->willReturn($category);

        $this->categoryRepository
            ->expects(self::once())
            ->method('update')
            ->with(self::callback(static function (Category $c) {
                return $c->getName() === 'New Name'
                    && $c->getDescription() === 'New desc'
                    && $c->isEssential() === true;
            }));

        $this->subject->updateCategory(5, 'New Name', 'New desc', true);
    }

    #[Test]
    public function updateCategoryDoesNothingWhenCategoryNotFound(): void
    {
        $this->categoryRepository
            ->expects(self::once())
            ->method('findByUid')
            ->with(999)
            ->willReturn(null);

        $this->categoryRepository
            ->expects(self::never())
            ->method('update');

        $this->subject->updateCategory(999, 'Name', 'Desc', false);
    }

    #[Test]
    public function reorderCategoriesDelegatesToRepository(): void
    {
        $sortedUids = [3, 1, 2];

        $this->categoryRepository
            ->expects(self::once())
            ->method('updateSorting')
            ->with($sortedUids);

        $this->subject->reorderCategories($sortedUids);
    }
}
