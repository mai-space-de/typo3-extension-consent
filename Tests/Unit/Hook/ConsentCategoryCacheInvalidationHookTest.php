<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Hook;

use Maispace\MaiConsent\Hook\ConsentCategoryCacheInvalidationHook;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Extbase\Service\CacheService;

final class ConsentCategoryCacheInvalidationHookTest extends TestCase
{
    private CacheService&MockObject $cacheService;

    private CacheManager&MockObject $cacheManager;

    private DataHandler&MockObject $dataHandler;

    protected function setUp(): void
    {
        $this->cacheService = $this->createMock(CacheService::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->dataHandler = $this->createMock(DataHandler::class);
    }

    #[Test]
    public function categoryUpdateFlushesExtbaseCacheTagsTest(): void
    {
        $this->cacheService->expects(self::once())
            ->method('clearCacheForRecord')
            ->with('tx_maiconsent_domain_model_consentcategory', 3);
        $this->cacheService->expects(self::once())->method('clearCachesOfRegisteredPageIds');
        $this->cacheManager->expects(self::once())->method('flushCachesByTag')->with('tx_maiconsent_banner');

        (new ConsentCategoryCacheInvalidationHook($this->cacheService, $this->cacheManager))->processDatamap_afterDatabaseOperations(
            'update',
            'tx_maiconsent_domain_model_consentcategory',
            3,
            ['title' => 'Marketing'],
            $this->dataHandler,
        );
    }

    #[Test]
    public function otherTablesAreIgnoredTest(): void
    {
        $this->cacheService->expects(self::never())->method('clearCacheForRecord');
        $this->cacheManager->expects(self::never())->method('flushCachesByTag');

        (new ConsentCategoryCacheInvalidationHook($this->cacheService, $this->cacheManager))->processDatamap_afterDatabaseOperations(
            'update',
            'tt_content',
            3,
            [],
            $this->dataHandler,
        );
    }
}
