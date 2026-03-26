<?php

declare(strict_types = 1);

namespace Maispace\MaiConsent\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;

/**
 * Provides merged consent settings: TypoScript plugin.tx_mai_consent
 * overrides built-in defaults so every option is always populated.
 */
class ConsentSettingsService
{
    private const TS_PLUGIN_KEY = 'tx_mai_consent.';

    /**
     * Maps TypoScript view path keys (with trailing dot) to the corresponding
     * settings array key (without trailing dot).
     */
    private const VIEW_PATH_KEYS = [
        'templateRootPaths.' => 'templateRootPaths',
        'partialRootPaths.'  => 'partialRootPaths',
        'layoutRootPaths.'   => 'layoutRootPaths',
    ];

    /**
     * Returns the effective settings for the current request.
     *
     * Settings are read from the TypoScript setup attribute attached to the
     * PSR-7 request by TYPO3's frontend stack.  When the attribute is absent
     * (e.g. in a backend or API context) the built-in defaults are returned.
     *
     * @return array<string, mixed>
     */
    public function getSettings(ServerRequestInterface $request): array
    {
        $defaults = $this->getDefaults();

        $tsPlugin = $this->readTypoScriptPlugin($request);
        if ($tsPlugin === []) {
            return $defaults;
        }

        return $this->applyTypoScriptSettings($defaults, $tsPlugin);
    }

    // -------------------------------------------------------------------------
    // Defaults
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        return [
            'cookie' => [
                'name'     => 'mai_consent',
                'lifetime' => 365,
                'sameSite' => 'Lax',
            ],
            'banner' => [
                'enable'          => 1,
                'position'        => 'bottom',
                'showOnEveryPage' => 0,
            ],
            'modal' => [
                'showCategoryDescriptions' => 1,
            ],
            'record' => [
                'endpoint' => '/maispace/consent/record',
            ],
            'statistics' => [
                'enable'        => 1,
                'retentionDays' => 90,
            ],
            'view' => [
                'templateRootPaths' => [
                    '0' => 'EXT:mai_consent/Resources/Private/Templates/',
                ],
                'partialRootPaths' => [
                    '0' => 'EXT:mai_consent/Resources/Private/Partials/',
                ],
                'layoutRootPaths' => [
                    '0' => 'EXT:mai_consent/Resources/Private/Layouts/',
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // TypoScript reading
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function readTypoScriptPlugin(
        ServerRequestInterface $request,
    ): array {
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        if (!$frontendTypoScript instanceof FrontendTypoScript) {
            return [];
        }

        $setup = [];
        try {
            $setup = $frontendTypoScript->getSetupArray();
        } catch (\Exception $e) {
            return [];
        }

        $pluginSetup = $setup['plugin.'] ?? null;
        if (!is_array($pluginSetup)) {
            return [];
        }

        $plugin = $pluginSetup[self::TS_PLUGIN_KEY] ?? null;

        /** @var array<string, mixed> $result */
        $result = is_array($plugin) ? $plugin : [];

        return $result;
    }

    /**
     * Merges TypoScript values into the defaults array.
     *
     * In the TypoScript PHP representation, sub-objects have keys ending with
     * a dot (e.g. `'cookie.'`) and scalar values do not (e.g. `'name'`).
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $ts       Raw TypoScript sub-array for plugin.tx_mai_consent
     *
     * @return array<string, mixed>
     */
    private function applyTypoScriptSettings(array $defaults, array $ts): array
    {
        $result = $defaults;

        // cookie.*
        $cookieTs = $ts['cookie.'] ?? null;
        if (is_array($cookieTs)) {
            $cookieResult = is_array($result['cookie'] ?? null)
                ? $result['cookie']
                : [];
            $name = $cookieTs['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $cookieResult['name'] = $name;
            }
            $lifetime = $cookieTs['lifetime'] ?? null;
            if (is_numeric($lifetime) && (int)$lifetime > 0) {
                $cookieResult['lifetime'] = (int)$lifetime;
            }
            $sameSite = $cookieTs['sameSite'] ?? null;
            if (is_string($sameSite) && $sameSite !== '') {
                $cookieResult['sameSite'] = $sameSite;
            }
            $result['cookie'] = $cookieResult;
        }

        // banner.*
        $bannerTs = $ts['banner.'] ?? null;
        if (is_array($bannerTs)) {
            $bannerResult = is_array($result['banner'] ?? null)
                ? $result['banner']
                : [];
            $enable = $bannerTs['enable'] ?? null;
            if ($enable !== null && is_numeric($enable)) {
                $bannerResult['enable'] = (int)$enable;
            }
            $position = $bannerTs['position'] ?? null;
            if (is_string($position) && $position !== '') {
                $bannerResult['position'] = $position;
            }
            $showOnEveryPage = $bannerTs['showOnEveryPage'] ?? null;
            if ($showOnEveryPage !== null && is_numeric($showOnEveryPage)) {
                $bannerResult['showOnEveryPage'] = (int)$showOnEveryPage;
            }
            $result['banner'] = $bannerResult;
        }

        // modal.*
        $modalTs = $ts['modal.'] ?? null;
        if (is_array($modalTs)) {
            $modalResult = is_array($result['modal'] ?? null)
                ? $result['modal']
                : [];
            $showCategoryDescriptions =
                $modalTs['showCategoryDescriptions'] ?? null;
            if (
                $showCategoryDescriptions !== null
                && is_numeric($showCategoryDescriptions)
            ) {
                $modalResult[
                    'showCategoryDescriptions'
                ] = (int)$showCategoryDescriptions;
            }
            $result['modal'] = $modalResult;
        }

        // record.*
        $recordTs = $ts['record.'] ?? null;
        if (is_array($recordTs)) {
            $recordResult = is_array($result['record'] ?? null)
                ? $result['record']
                : [];
            $endpoint = $recordTs['endpoint'] ?? null;
            if (is_string($endpoint) && $endpoint !== '') {
                $recordResult['endpoint'] = $endpoint;
            }
            $result['record'] = $recordResult;
        }

        // statistics.*
        $statisticsTs = $ts['statistics.'] ?? null;
        if (is_array($statisticsTs)) {
            $statsResult = is_array($result['statistics'] ?? null)
                ? $result['statistics']
                : [];
            $enable = $statisticsTs['enable'] ?? null;
            if ($enable !== null && is_numeric($enable)) {
                $statsResult['enable'] = (int)$enable;
            }
            $retentionDays = $statisticsTs['retentionDays'] ?? null;
            if (
                $retentionDays !== null
                && is_numeric($retentionDays)
                && (int)$retentionDays >= 0
            ) {
                $statsResult['retentionDays'] = (int)$retentionDays;
            }
            $result['statistics'] = $statsResult;
        }

        // view.* — handle path arrays (templateRootPaths, partialRootPaths, layoutRootPaths)
        $viewTs = $ts['view.'] ?? null;
        if (is_array($viewTs)) {
            $viewResult = is_array($result['view'] ?? null)
                ? $result['view']
                : [];
            foreach (self::VIEW_PATH_KEYS as $tsKey => $settingsKey) {
                $pathsArray = $viewTs[$tsKey] ?? null;
                if (!is_array($pathsArray)) {
                    continue;
                }
                $paths = [];
                foreach ($pathsArray as $idx => $path) {
                    // Accept both integer and string indices; skip dot-suffix sub-object keys
                    if (is_string($idx) && str_ends_with($idx, '.')) {
                        continue;
                    }
                    if (is_string($path) && $path !== '') {
                        $paths[(string)$idx] = $path;
                    }
                }
                if ($paths !== []) {
                    $viewResult[$settingsKey] = $paths;
                }
            }
            $result['view'] = $viewResult;
        }

        return $result;
    }
}
