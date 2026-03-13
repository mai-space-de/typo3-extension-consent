<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Service;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class BannerRenderer
{
    private const EXT_KEY = 'maispace_consent';
    private const DEFAULT_PARTIALS_SUBPATH = 'Resources/Private/Partials/';
    private const DEFAULT_LAYOUTS_SUBPATH  = 'Resources/Private/Layouts/';

    /**
     * @param array<string, mixed> $variables
     */
    public function renderBannerHtml(array $variables): string
    {
        $extPath = ExtensionManagementUtility::extPath(self::EXT_KEY);
        $viewSettings = $this->extractViewSettings($variables);

        $partialRootPaths = $this->resolveRootPaths(
            $viewSettings['partialRootPaths'] ?? [],
            $extPath . self::DEFAULT_PARTIALS_SUBPATH
        );
        $layoutRootPaths = $this->resolveRootPaths(
            $viewSettings['layoutRootPaths'] ?? [],
            $extPath . self::DEFAULT_LAYOUTS_SUBPATH
        );

        $templateFile = $this->resolveOverridableTemplate($partialRootPaths, 'Consent/Banner.html')
            ?? $extPath . self::DEFAULT_PARTIALS_SUBPATH . 'Consent/Banner.html';

        $view = new StandaloneView();
        $view->setTemplatePathAndFilename($templateFile);
        $view->setPartialRootPaths($partialRootPaths);
        $view->setLayoutRootPaths($layoutRootPaths);
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
        $viewSettings = $this->extractViewSettings($variables);

        $partialRootPaths = $this->resolveRootPaths(
            $viewSettings['partialRootPaths'] ?? [],
            $extPath . self::DEFAULT_PARTIALS_SUBPATH
        );
        $layoutRootPaths = $this->resolveRootPaths(
            $viewSettings['layoutRootPaths'] ?? [],
            $extPath . self::DEFAULT_LAYOUTS_SUBPATH
        );

        $templateFile = $this->resolveOverridableTemplate($partialRootPaths, 'Consent/Modal.html')
            ?? $extPath . self::DEFAULT_PARTIALS_SUBPATH . 'Consent/Modal.html';

        $view = new StandaloneView();
        $view->setTemplatePathAndFilename($templateFile);
        $view->setPartialRootPaths($partialRootPaths);
        $view->setLayoutRootPaths($layoutRootPaths);
        $view->assignMultiple($variables);

        $html = $view->render();

        return is_string($html) ? $html : '';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function extractViewSettings(array $variables): array
    {
        $settings = is_array($variables['settings'] ?? null) ? $variables['settings'] : [];

        return is_array($settings['view'] ?? null) ? $settings['view'] : [];
    }

    /**
     * Converts a settings path array (keyed by TypoScript index) to an ordered
     * list of resolved absolute filesystem paths.
     *
     * The default path is prepended at the lowest priority so that site-package
     * overrides at index 10, 20, etc. take precedence.
     *
     * @param array<string, string> $configuredPaths  e.g. ['0' => 'EXT:...', '10' => 'EXT:...']
     * @param string $defaultPath  Absolute fallback path
     * @return string[]
     */
    private function resolveRootPaths(array $configuredPaths, string $defaultPath): array
    {
        if ($configuredPaths === []) {
            return [$defaultPath];
        }

        // Sort by key ascending so higher indices override lower ones
        ksort($configuredPaths);

        $resolved = [];
        foreach ($configuredPaths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            if (str_starts_with($path, 'EXT:')) {
                $resolvedPath = GeneralUtility::getFileAbsFileName($path);
                if ($resolvedPath === false || $resolvedPath === '') {
                    continue;
                }
                $path = $resolvedPath;
            }
            if ($path !== '') {
                $resolved[] = rtrim($path, '/') . '/';
            }
        }

        return $resolved !== [] ? $resolved : [$defaultPath];
    }

    /**
     * Searches the given root paths (highest index = highest priority, so
     * iterate in reverse) for a partial template file and returns the first
     * match, or null if none is found.
     *
     * @param string[] $rootPaths  Resolved absolute partial root paths
     * @param string $relativePath  e.g. 'Consent/Banner.html'
     */
    private function resolveOverridableTemplate(array $rootPaths, string $relativePath): ?string
    {
        // Iterate in reverse: later entries (higher TypoScript index) win
        foreach (array_reverse($rootPaths) as $rootPath) {
            $candidate = rtrim($rootPath, '/') . '/' . ltrim($relativePath, '/');
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
