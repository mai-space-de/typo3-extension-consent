<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Tests\Unit\Service;

use Maispace\MaispaceConsent\Service\ConsentSettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;

#[CoversClass(ConsentSettingsService::class)]
final class ConsentSettingsServiceTest extends TestCase
{
    private ConsentSettingsService $subject;

    protected function setUp(): void
    {
        $this->subject = new ConsentSettingsService();
    }

    // -------------------------------------------------------------------------
    // Defaults (no TypoScript attribute)
    // -------------------------------------------------------------------------

    #[Test]
    public function returnsDefaultsWhenNoTypoScriptAttributePresent(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('frontend.typoscript')->willReturn(null);

        $settings = $this->subject->getSettings($request);

        self::assertSame('maispace_consent', $settings['cookie']['name']);
        self::assertSame(365, $settings['cookie']['lifetime']);
        self::assertSame('Lax', $settings['cookie']['sameSite']);
        self::assertSame(1, $settings['banner']['enable']);
        self::assertSame('bottom', $settings['banner']['position']);
        self::assertSame(0, $settings['banner']['showOnEveryPage']);
        self::assertSame(1, $settings['modal']['showCategoryDescriptions']);
        self::assertSame('/maispace/consent/record', $settings['record']['endpoint']);
        self::assertSame(1, $settings['statistics']['enable']);
        self::assertSame(90, $settings['statistics']['retentionDays']);
    }

    #[Test]
    public function returnsDefaultsWhenTypoScriptAttributeIsNotFrontendTypoScript(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('frontend.typoscript')->willReturn(new \stdClass());

        $settings = $this->subject->getSettings($request);

        self::assertSame('maispace_consent', $settings['cookie']['name']);
        self::assertSame(1, $settings['banner']['enable']);
    }

    // -------------------------------------------------------------------------
    // TypoScript overrides
    // -------------------------------------------------------------------------

    #[Test]
    public function typoScriptCookieSettingsOverrideDefaults(): void
    {
        $tsPlugin = [
            'cookie.' => [
                'name'     => 'custom_consent',
                'lifetime' => '730',
                'sameSite' => 'Strict',
            ],
        ];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame('custom_consent', $settings['cookie']['name']);
        self::assertSame(730, $settings['cookie']['lifetime']);
        self::assertSame('Strict', $settings['cookie']['sameSite']);
    }

    #[Test]
    public function typoScriptBannerEnableZeroIsRespected(): void
    {
        $tsPlugin = ['banner.' => ['enable' => '0']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame(0, $settings['banner']['enable']);
    }

    #[Test]
    public function typoScriptBannerPositionOverridesDefault(): void
    {
        $tsPlugin = ['banner.' => ['position' => 'top']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame('top', $settings['banner']['position']);
    }

    #[Test]
    public function typoScriptBannerShowOnEveryPageOneIsRespected(): void
    {
        $tsPlugin = ['banner.' => ['showOnEveryPage' => '1']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame(1, $settings['banner']['showOnEveryPage']);
    }

    #[Test]
    public function typoScriptStatisticsEnableZeroIsRespected(): void
    {
        $tsPlugin = ['statistics.' => ['enable' => '0']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame(0, $settings['statistics']['enable']);
    }

    #[Test]
    public function typoScriptStatisticsRetentionDaysOverridesDefault(): void
    {
        $tsPlugin = ['statistics.' => ['retentionDays' => '180']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame(180, $settings['statistics']['retentionDays']);
    }

    #[Test]
    public function typoScriptModalShowCategoryDescriptionsZeroIsRespected(): void
    {
        $tsPlugin = ['modal.' => ['showCategoryDescriptions' => '0']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame(0, $settings['modal']['showCategoryDescriptions']);
    }

    #[Test]
    public function typoScriptRecordEndpointOverridesDefault(): void
    {
        $tsPlugin = ['record.' => ['endpoint' => '/custom/record']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame('/custom/record', $settings['record']['endpoint']);
    }

    #[Test]
    public function typoScriptViewPartialRootPathsOverrideDefaults(): void
    {
        $tsPlugin = [
            'view.' => [
                'partialRootPaths.' => [
                    '0'  => 'EXT:maispace_consent/Resources/Private/Partials/',
                    '10' => 'EXT:my_sitepackage/Resources/Private/Partials/',
                ],
            ],
        ];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertArrayHasKey('0', $settings['view']['partialRootPaths']);
        self::assertArrayHasKey('10', $settings['view']['partialRootPaths']);
        self::assertSame('EXT:my_sitepackage/Resources/Private/Partials/', $settings['view']['partialRootPaths']['10']);
    }

    #[Test]
    public function typoScriptViewPathsWithIntegerKeysAreAccepted(): void
    {
        // PHP may store TypoScript numeric indices as integers, not strings
        $tsPlugin = [
            'view.' => [
                'partialRootPaths.' => [
                    0  => 'EXT:maispace_consent/Resources/Private/Partials/',
                    10 => 'EXT:my_sitepackage/Resources/Private/Partials/',
                ],
            ],
        ];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertArrayHasKey('0', $settings['view']['partialRootPaths']);
        self::assertArrayHasKey('10', $settings['view']['partialRootPaths']);
    }

    #[Test]
    public function retentionDaysNonNumericValueIsIgnored(): void
    {
        $tsPlugin = ['statistics.' => ['retentionDays' => 'invalid']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        // Non-numeric value must not override the 90-day default
        self::assertSame(90, $settings['statistics']['retentionDays']);
    }

    #[Test]
    public function retentionDaysZeroIsAccepted(): void
    {
        // retentionDays = 0 means "keep forever" and is a valid override
        $tsPlugin = ['statistics.' => ['retentionDays' => '0']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame(0, $settings['statistics']['retentionDays']);
    }

    #[Test]
    public function cookieLifetimeZeroOrNegativeIsIgnored(): void
    {
        $tsPlugin = ['cookie.' => ['lifetime' => '0']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        // Zero lifetime must not override the 365-day default
        self::assertSame(365, $settings['cookie']['lifetime']);
    }

    #[Test]
    public function emptyCookieNameIsIgnored(): void
    {
        $tsPlugin = ['cookie.' => ['name' => '']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame('maispace_consent', $settings['cookie']['name']);
    }

    #[Test]
    public function unrelatedTypoScriptKeysDoNotAffectSettings(): void
    {
        $tsPlugin = ['something.' => ['unrelated' => 'value']];

        $settings = $this->subject->getSettings($this->buildRequest($tsPlugin));

        self::assertSame('maispace_consent', $settings['cookie']['name']);
        self::assertSame(1, $settings['banner']['enable']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Builds a mock request that exposes $tsPlugin as the tx_maispace_consent
     * section of the TypoScript setup.
     *
     * @param array<string, mixed> $tsPlugin
     */
    private function buildRequest(array $tsPlugin): ServerRequestInterface
    {
        $setup = [
            'plugin.' => [
                'tx_maispace_consent.' => $tsPlugin,
            ],
        ];

        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray($setup);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('frontend.typoscript')->willReturn($frontendTypoScript);

        return $request;
    }
}
