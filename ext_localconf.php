<?php

declare(strict_types=1);

defined('TYPO3') or die();

(static function (): void {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'MaiConsent',
        'Banner',
        [\Maispace\MaiConsent\Controller\Frontend\BannerController::class => 'index'],
        [],
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptConstants(
        '@import "EXT:mai_consent/Configuration/TypoScript/constants.typoscript"'
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
        '@import "EXT:mai_consent/Configuration/TypoScript/setup.typoscript"'
    );

    // Register the global Fluid namespace <consent:…> so templates can resolve
    // <consent:gate category="…">…</consent:gate> without an inline xmlns:consent.
    // The PHP namespace is singular ("ViewHelper") on purpose — mirror it here.
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['consent'][]
        = 'Maispace\\MaiConsent\\ViewHelper';
})();
