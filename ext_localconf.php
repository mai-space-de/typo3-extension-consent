<?php

declare(strict_types=1);

defined('TYPO3') or die();

call_user_func(static function (): void {
    // Register the mai_consent caching framework cache.
    // Stores consent-related data. Grouped with pages and all so a page cache flush
    // also clears consent caches — ensures fresh data is served.
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['mai_consent'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['mai_consent'] = [
            'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
            'backend'  => \TYPO3\CMS\Core\Cache\Backend\FileBackend::class,
            'options'  => [
                'defaultLifetime' => 0, // permanent until flushed
            ],
            'groups'   => ['pages', 'all'],
        ];
    }
});
