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

    public function testFindOneByIdentifierDisablesStoragePageRespect(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/Classes/Domain/Repository/ConsentCategoryRepository.php',
        );
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/function findOneByIdentifier[\s\S]*setRespectStoragePage\(false\)/',
            $source,
            'findOneByIdentifier must ignore storagePid so the consent API can resolve categories without Extbase TypoScript context.',
        );
    }

    public function testFindAllOrderedDisablesStoragePageRespect(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/Classes/Domain/Repository/ConsentCategoryRepository.php',
        );
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/function findAllOrdered[\s\S]*setRespectStoragePage\(false\)/',
            $source,
            'findAllOrdered must ignore storagePid so the backend statistics module lists categories when the page tree selection is not the storage folder.',
        );
    }
}
