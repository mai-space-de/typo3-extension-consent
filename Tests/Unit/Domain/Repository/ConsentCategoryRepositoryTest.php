<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Domain\Repository;

use Maispace\MaiConsent\Domain\Repository\ConsentCategoryRepository;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ConsentCategoryRepositoryTest extends TestCase
{
    public function testClassExtendsExtbaseRepository(): void
    {
        self::assertTrue(
            is_subclass_of(ConsentCategoryRepository::class, Repository::class),
        );
    }

    public function testNoDefaultOrderingsAreSet(): void
    {
        $reflection = new \ReflectionClass(ConsentCategoryRepository::class);
        $defaultOrderings = $reflection->getDefaultProperties()['defaultOrderings'] ?? [];
        self::assertSame([], $defaultOrderings, 'findAllOrdered() sets ordering at query time; no static defaultOrderings expected');
    }
}
