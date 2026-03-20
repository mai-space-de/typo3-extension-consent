<?php

declare(strict_types = 1);

namespace Maispace\MaiConsent\Service;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class BannerRenderer
{
    private const EXT_KEY = 'mai_consent';
    private const DEFAULT_PARTIALS_SUBPATH = 'Resources/Private/Partials/';
    private const DEFAULT_LAYOUTS_SUBPATH = 'Resources/Private/Layouts/';

    /**
     * @param array<string, mixed> $variables
     */
    public function renderBannerHtml(array $variables): string
    {
        $extPath = ExtensionManagementUtility::extPath(self::EXT_KEY);
        $viewSettings = $this->extractViewSettings($variables);

        $configuredPartials = $viewSettings['partialRootPaths'] ?? [];
        $partialRootPaths = $this->resolveRootPaths(
            is_array($configuredPartials) ? $configuredPartials : [],
            $extPath . self::DEFAULT_PARTIALS_SUBPATH
        );
        $configuredLayouts = $viewSettings['layoutRootPaths'] ?? [];
        $layoutRootPaths = $this->resolveRootPaths(
            is_array($configuredLayouts) ? $configuredLayouts : [],
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

        $configuredPartials = $viewSettings['partialRootPaths'] ?? [];
        $partialRootPaths = $this->resolveRootPaths(
            is_array($configuredPartials) ? $configuredPartials : [],
            $extPath . self::DEFAULT_PARTIALS_SUBPATH
        );
        $configuredLayouts = $viewSettings['layoutRootPaths'] ?? [];
        $layoutRootPaths = $this->resolveRootPaths(
            is_array($configuredLayouts) ? $configuredLayouts : [],
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
     *
     * @return array<string, mixed>
     */
    private function extractViewSettings(array $variables): array
    {
        $settings = is_array($variables['settings'] ?? null) ? $variables['settings'] : [];
        $view = $settings['view'] ?? null;

        /** @var array<string, mixed> $result */
        $result = is_array($view) ? $view : [];

        return $result;
    }

    /**
     * Converts a settings path array (keyed by TypoScript index) to an ordered
     * list of resolved absolute filesystem paths.
     *
     * The default extension path is always prepended at the lowest priority so
     * that Fluid can fall back to the built-in templates even when a site
     * package provides only partial overrides.  Site-package overrides at
     * higher indices take precedence over lower ones.
     *
     * @param array<int|string, mixed> $configuredPaths e.g. ['0' => 'EXT:...', '10' => 'EXT:...']
     * @param string                   $defaultPath     Absolute fallback path (always included)
     *
     * @return string[]
     */
    private function resolveRootPaths(array $configuredPaths, string $defaultPath): array
    {
        // Sort by key ascending so higher indices override lower ones
        ksort($configuredPaths);

        $resolved = [];
        foreach ($configuredPaths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            if (str_starts_with($path, 'EXT:')) {
                $resolvedPath = GeneralUtility::getFileAbsFileName($path);
                if ($resolvedPath === '') {
                    continue;
                }
                $path = $resolvedPath;
            }
            $normalised = rtrim($path, '/') . '/';
            $resolved[] = $normalised;
        }

        // Always ensure the default path is present (at lowest priority)
        $normalisedDefault = rtrim($defaultPath, '/') . '/';
        if (!in_array($normalisedDefault, $resolved, true)) {
            array_unshift($resolved, $normalisedDefault);
        }

        return $resolved;
    }

    /**
     * Searches the given root paths (highest index = highest priority, so
     * iterate in reverse) for a partial template file and returns the first
     * match, or null if none is found.
     *
     * @param string[] $rootPaths    Resolved absolute partial root paths
     * @param string   $relativePath e.g. 'Consent/Banner.html'
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
