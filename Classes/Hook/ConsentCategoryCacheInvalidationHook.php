<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Hook;

use Maispace\MaiBase\Hook\AbstractRecordCacheInvalidationHook;
use Maispace\MaiConsent\Controller\Frontend\BannerController;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Extbase\Service\CacheService;

/**
 * Flushes the page cache tags of the consent banner when a consent category
 * is saved or deleted.
 *
 * The banner is part of the cached page output (it only contains the category
 * list and a version hash, never visitor state), so edits must invalidate it.
 */
final class ConsentCategoryCacheInvalidationHook extends AbstractRecordCacheInvalidationHook
{
    public function __construct(
        CacheService $cacheService,
        private readonly CacheManager $cacheManager,
    ) {
        parent::__construct($cacheService);
    }

    protected function flushCachesForRecord(string $table, int $uid): void
    {
        parent::flushCachesForRecord($table, $uid);
        $this->cacheManager->flushCachesByTag(BannerController::CACHE_TAG);
    }

    protected function getWatchedTable(): string
    {
        return 'tx_maiconsent_domain_model_consentcategory';
    }
}
