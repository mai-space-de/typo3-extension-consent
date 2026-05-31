<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Configuration;

use Maispace\MaiConsent\ViewHelper\GateViewHelper;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that ext_localconf.php registers the global Fluid namespace
 * <consent:…> so that <consent:gate> resolves to GateViewHelper without
 * requiring an inline xmlns:consent on every template.
 *
 * Regression test for the smoke-test pages (pid 10001 / 10002) returning
 * HTTP 500: "ViewHelper <consent:gate> could not be resolved."
 */
final class FluidNamespaceRegistrationTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $previousConfVars = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousConfVars = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        // Reset to a known empty state so the registration can be observed cleanly.
        $GLOBALS['TYPO3_CONF_VARS'] = [];
        if (!\defined('TYPO3')) {
            \define('TYPO3', 'TEST');
        }
    }

    protected function tearDown(): void
    {
        if ($this->previousConfVars === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS'] = $this->previousConfVars;
        }

        parent::tearDown();
    }

    public function testExtLocalconfRegistersConsentFluidNamespace(): void
    {
        $this->loadExtLocalconfBootstrappedRegistration();

        self::assertArrayHasKey('SYS', $GLOBALS['TYPO3_CONF_VARS']);
        self::assertArrayHasKey('fluid', $GLOBALS['TYPO3_CONF_VARS']['SYS']);
        self::assertArrayHasKey('namespaces', $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']);
        self::assertArrayHasKey('consent', $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']);

        $namespaces = $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['consent'];
        self::assertContains(
            'Maispace\\MaiConsent\\ViewHelper',
            $namespaces,
            'consent Fluid namespace must map to the Maispace\\MaiConsent\\ViewHelper PHP namespace.',
        );
    }

    public function testGateViewHelperLivesInRegisteredPhpNamespace(): void
    {
        // The PHP namespace registered for <consent:…> must actually contain
        // GateViewHelper so that <consent:gate> resolves at render time.
        self::assertSame(
            'Maispace\\MaiConsent\\ViewHelper',
            (new \ReflectionClass(GateViewHelper::class))->getNamespaceName(),
        );
    }

    /**
     * Re-applies the namespace registration block from ext_localconf.php.
     *
     * The full ext_localconf.php cannot be required directly here because it
     * calls ExtensionUtility::configurePlugin() which depends on the TYPO3
     * runtime. We replay only the namespace registration — the exact contract
     * we want to lock down with a regression test.
     */
    private function loadExtLocalconfBootstrappedRegistration(): void
    {
        $extLocalconf = file_get_contents(__DIR__ . '/../../../ext_localconf.php');
        self::assertIsString($extLocalconf, 'ext_localconf.php must be readable.');

        self::assertMatchesRegularExpression(
            "/\\\$GLOBALS\\['TYPO3_CONF_VARS'\\]\\['SYS'\\]\\['fluid'\\]\\['namespaces'\\]\\['consent'\\]"
            . "\\[\\]\\s*=\\s*'Maispace\\\\\\\\MaiConsent\\\\\\\\ViewHelper';/",
            $extLocalconf,
            'ext_localconf.php must register the consent Fluid namespace.',
        );

        // Mirror the exact statement so the assertions above operate on
        // realistic runtime state, not just on textual contents.
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['consent'][]
            = 'Maispace\\MaiConsent\\ViewHelper';
    }
}
