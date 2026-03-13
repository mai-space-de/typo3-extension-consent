<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;

/**
 * Provides merged consent settings: TypoScript plugin.tx_maispace_consent
 * overrides built-in defaults so every option is always populated.
 */
class ConsentSettingsService
{
    private const TS_PLUGIN_KEY = 'tx_maispace_consent.';

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
                'name'     => 'maispace_consent',
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
                'templateRootPaths' => ['0' => 'EXT:maispace_consent/Resources/Private/Templates/'],
                'partialRootPaths'  => ['0' => 'EXT:maispace_consent/Resources/Private/Partials/'],
                'layoutRootPaths'   => ['0' => 'EXT:maispace_consent/Resources/Private/Layouts/'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // TypoScript reading
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function readTypoScriptPlugin(ServerRequestInterface $request): array
    {
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        if (!$frontendTypoScript instanceof FrontendTypoScript) {
            return [];
        }

        $setup = $frontendTypoScript->getSetupArray();
        $plugin = $setup['plugin.'][self::TS_PLUGIN_KEY] ?? null;

        return is_array($plugin) ? $plugin : [];
    }

    /**
     * Merges TypoScript values into the defaults array.
     *
     * In the TypoScript PHP representation, sub-objects have keys ending with
     * a dot (e.g. `'cookie.'`) and scalar values do not (e.g. `'name'`).
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $ts  Raw TypoScript sub-array for plugin.tx_maispace_consent
     * @return array<string, mixed>
     */
    private function applyTypoScriptSettings(array $defaults, array $ts): array
    {
        $result = $defaults;

        // cookie.*
        if (isset($ts['cookie.']) && is_array($ts['cookie.'])) {
            $c = $ts['cookie.'];
            if (isset($c['name']) && is_string($c['name']) && $c['name'] !== '') {
                $result['cookie']['name'] = $c['name'];
            }
            if (isset($c['lifetime']) && (int)$c['lifetime'] > 0) {
                $result['cookie']['lifetime'] = (int)$c['lifetime'];
            }
            if (isset($c['sameSite']) && is_string($c['sameSite']) && $c['sameSite'] !== '') {
                $result['cookie']['sameSite'] = $c['sameSite'];
            }
        }

        // banner.*
        if (isset($ts['banner.']) && is_array($ts['banner.'])) {
            $b = $ts['banner.'];
            if (array_key_exists('enable', $b)) {
                $result['banner']['enable'] = (int)$b['enable'];
            }
            if (isset($b['position']) && is_string($b['position']) && $b['position'] !== '') {
                $result['banner']['position'] = $b['position'];
            }
            if (array_key_exists('showOnEveryPage', $b)) {
                $result['banner']['showOnEveryPage'] = (int)$b['showOnEveryPage'];
            }
        }

        // modal.*
        if (isset($ts['modal.']) && is_array($ts['modal.'])) {
            $m = $ts['modal.'];
            if (array_key_exists('showCategoryDescriptions', $m)) {
                $result['modal']['showCategoryDescriptions'] = (int)$m['showCategoryDescriptions'];
            }
        }

        // record.*
        if (isset($ts['record.']) && is_array($ts['record.'])) {
            $r = $ts['record.'];
            if (isset($r['endpoint']) && is_string($r['endpoint']) && $r['endpoint'] !== '') {
                $result['record']['endpoint'] = $r['endpoint'];
            }
        }

        // statistics.*
        if (isset($ts['statistics.']) && is_array($ts['statistics.'])) {
            $s = $ts['statistics.'];
            if (array_key_exists('enable', $s)) {
                $result['statistics']['enable'] = (int)$s['enable'];
            }
            if (array_key_exists('retentionDays', $s) && is_numeric($s['retentionDays']) && (int)$s['retentionDays'] >= 0) {
                $result['statistics']['retentionDays'] = (int)$s['retentionDays'];
            }
        }

        // view.* — handle path arrays (templateRootPaths, partialRootPaths, layoutRootPaths)
        if (isset($ts['view.']) && is_array($ts['view.'])) {
            $v = $ts['view.'];
            foreach (self::VIEW_PATH_KEYS as $tsKey => $settingsKey) {
                if (!isset($v[$tsKey]) || !is_array($v[$tsKey])) {
                    continue;
                }
                $paths = [];
                foreach ($v[$tsKey] as $idx => $path) {
                    // Accept both integer and string indices; skip dot-suffix sub-object keys
                    if (is_string($idx) && str_ends_with($idx, '.')) {
                        continue;
                    }
                    if (is_string($path) && $path !== '') {
                        $paths[(string)$idx] = $path;
                    }
                }
                if ($paths !== []) {
                    $result['view'][$settingsKey] = $paths;
                }
            }
        }

        return $result;
    }
}
