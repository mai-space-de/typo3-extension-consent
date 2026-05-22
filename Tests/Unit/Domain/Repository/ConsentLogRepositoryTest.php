<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Domain\Repository;

use Maispace\MaiConsent\Domain\Repository\ConsentLogRepository;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ConsentLogRepositoryTest extends TestCase
{
    public function testClassExtendsExtbaseRepository(): void
    {
        self::assertTrue(
            is_subclass_of(ConsentLogRepository::class, Repository::class),
        );
    }

    public function testNoDefaultOrderingsAreSet(): void
    {
        $reflection = new \ReflectionClass(ConsentLogRepository::class);
        $defaultOrderings = $reflection->getDefaultProperties()['defaultOrderings'] ?? [];
        self::assertSame([], $defaultOrderings, 'ConsentLogRepository uses direct SQL queries; no static defaultOrderings expected');
    }
}
