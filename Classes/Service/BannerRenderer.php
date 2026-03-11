<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Service;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class BannerRenderer
{
    private const EXT_KEY = 'maispace_consent';

    /**
     * @param array<string, mixed> $variables
     */
    public function renderBannerHtml(array $variables): string
    {
        $extPath = ExtensionManagementUtility::extPath(self::EXT_KEY);

        $view = new StandaloneView();
        $view->setTemplatePathAndFilename($extPath . 'Resources/Private/Partials/Consent/Banner.html');
        $view->setPartialRootPaths([$extPath . 'Resources/Private/Partials/']);
        $view->setLayoutRootPaths([$extPath . 'Resources/Private/Layouts/']);
        $view->assignMultiple($variables);

        $html = $view->render();

        return is_string($html) ? $html : '';
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function renderModalHtml(array $variables): string
    {
        $extPath = ExtensionManagementUtility::extPath(self::EXT_KEY);

        $view = new StandaloneView();
        $view->setTemplatePathAndFilename($extPath . 'Resources/Private/Partials/Consent/Modal.html');
        $view->setPartialRootPaths([$extPath . 'Resources/Private/Partials/']);
        $view->setLayoutRootPaths([$extPath . 'Resources/Private/Layouts/']);
        $view->assignMultiple($variables);

        $html = $view->render();

        return is_string($html) ? $html : '';
    }

    public function getJsPath(): string
    {
        $extPath = ExtensionManagementUtility::extPath(self::EXT_KEY);

        return PathUtility::getAbsoluteWebPath($extPath . 'Resources/Public/JavaScript/consent.js');
    }
}
