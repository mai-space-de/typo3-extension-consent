<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Controller\Frontend;

use Maispace\MaiConsent\Controller\Frontend\BannerController;
use Maispace\MaiConsent\Domain\Model\ConsentCategory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BannerControllerTest extends TestCase
{
    private static function makeCategory(string $identifier): ConsentCategory
    {
        $cat = new ConsentCategory();
        $cat->setIdentifier($identifier);
        return $cat;
    }

    #[Test]
    public function emptyListProducesConsistentHash(): void
    {
        $hash = BannerController::computeCategoryVersion([]);
        self::assertSame(8, strlen($hash));
        self::assertSame($hash, BannerController::computeCategoryVersion([]));
    }

    #[Test]
    public function singleCategoryProducesEightCharHash(): void
    {
        $hash = BannerController::computeCategoryVersion([self::makeCategory('analytics')]);
        self::assertSame(8, strlen($hash));
    }

    #[Test]
    public function sameCategoriesProduceSameHash(): void
    {
        $a = BannerController::computeCategoryVersion([
            self::makeCategory('analytics'),
            self::makeCategory('marketing'),
        ]);
        $b = BannerController::computeCategoryVersion([
            self::makeCategory('analytics'),
            self::makeCategory('marketing'),
        ]);
        self::assertSame($a, $b);
    }

    #[Test]
    public function orderDoesNotAffectHash(): void
    {
        $a = BannerController::computeCategoryVersion([
            self::makeCategory('analytics'),
            self::makeCategory('marketing'),
        ]);
        $b = BannerController::computeCategoryVersion([
            self::makeCategory('marketing'),
            self::makeCategory('analytics'),
        ]);
        self::assertSame($a, $b);
    }

    #[Test]
    public function addingCategoryChangesHash(): void
    {
        $before = BannerController::computeCategoryVersion([
            self::makeCategory('analytics'),
        ]);
        $after = BannerController::computeCategoryVersion([
            self::makeCategory('analytics'),
            self::makeCategory('marketing'),
        ]);
        self::assertNotSame($before, $after);
    }

    #[Test]
    public function removingCategoryChangesHash(): void
    {
        $before = BannerController::computeCategoryVersion([
            self::makeCategory('analytics'),
            self::makeCategory('marketing'),
        ]);
        $after = BannerController::computeCategoryVersion([
            self::makeCategory('analytics'),
        ]);
        self::assertNotSame($before, $after);
    }

    #[Test]
    public function renamingCategoryChangesHash(): void
    {
        $before = BannerController::computeCategoryVersion([self::makeCategory('analytics')]);
        $after  = BannerController::computeCategoryVersion([self::makeCategory('analytics_v2')]);
        self::assertNotSame($before, $after);
    }
}
