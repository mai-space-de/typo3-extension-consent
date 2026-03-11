<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Tests\Unit\Service;

use Maispace\MaispaceConsent\Service\ConsentCookieService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(ConsentCookieService::class)]
final class ConsentCookieServiceTest extends TestCase
{
    private ConsentCookieService $subject;

    protected function setUp(): void
    {
        $this->subject = new ConsentCookieService();
    }

    #[Test]
    public function parseCookieValueReturnsEmptyArrayForEmptyString(): void
    {
        self::assertSame([], $this->subject->parseCookieValue(''));
    }

    #[Test]
    public function parseCookieValueReturnsEmptyArrayForInvalidJson(): void
    {
        self::assertSame([], $this->subject->parseCookieValue('not-json'));
    }

    #[Test]
    public function parseCookieValueReturnsEmptyArrayForJsonArray(): void
    {
        self::assertSame([], $this->subject->parseCookieValue('[1, 2, 3]'));
    }

    #[Test]
    public function parseCookieValueReturnsEmptyArrayForJsonNull(): void
    {
        self::assertSame([], $this->subject->parseCookieValue('null'));
    }

    #[Test]
    public function parseCookieValueReturnsPreferencesForValidJson(): void
    {
        $json = '{"1": true, "2": false, "3": true}';
        $result = $this->subject->parseCookieValue($json);

        self::assertTrue($result['1']);
        self::assertFalse($result['2']);
        self::assertTrue($result['3']);
    }

    #[Test]
    public function parseCookieValueSkipsNonBooleanValues(): void
    {
        $json = '{"1": true, "2": "yes", "3": 1}';
        $result = $this->subject->parseCookieValue($json);

        self::assertArrayHasKey('1', $result);
        self::assertArrayNotHasKey('2', $result);
        self::assertArrayNotHasKey('3', $result);
    }

    #[Test]
    public function hasConsentReturnsTrueWhenPreferenceIsTrue(): void
    {
        $preferences = ['1' => true, '2' => false];

        self::assertTrue($this->subject->hasConsent($preferences, 1));
    }

    #[Test]
    public function hasConsentReturnsFalseWhenPreferenceIsFalse(): void
    {
        $preferences = ['1' => true, '2' => false];

        self::assertFalse($this->subject->hasConsent($preferences, 2));
    }

    #[Test]
    public function hasConsentReturnsFalseWhenCategoryNotInPreferences(): void
    {
        $preferences = ['1' => true];

        self::assertFalse($this->subject->hasConsent($preferences, 99));
    }

    #[Test]
    public function areAllDecidedReturnsTrueWhenAllCategoriesHaveDecision(): void
    {
        $preferences = ['1' => true, '2' => false, '3' => true];

        self::assertTrue($this->subject->areAllDecided($preferences, [1, 2, 3]));
    }

    #[Test]
    public function areAllDecidedReturnsTrueForEmptyCategoryList(): void
    {
        self::assertTrue($this->subject->areAllDecided([], []));
    }

    #[Test]
    public function areAllDecidedReturnsFalseWhenCategoryMissing(): void
    {
        $preferences = ['1' => true];

        self::assertFalse($this->subject->areAllDecided($preferences, [1, 2]));
    }

    #[Test]
    public function areAllDecidedReturnsFalseWhenNoDecisionsMadeYet(): void
    {
        self::assertFalse($this->subject->areAllDecided([], [1, 2, 3]));
    }

    #[Test]
    public function buildCookieValueReturnsValidJson(): void
    {
        $preferences = ['1' => true, '2' => false];
        $result = $this->subject->buildCookieValue($preferences);

        self::assertJson($result);
        $decoded = json_decode($result, true);
        self::assertIsArray($decoded);
        self::assertTrue($decoded['1']);
        self::assertFalse($decoded['2']);
    }

    #[Test]
    public function buildCookieValueReturnsEmptyJsonObjectForEmptyPreferences(): void
    {
        $result = $this->subject->buildCookieValue([]);

        self::assertSame('{}', $result);
    }

    #[Test]
    public function getPreferencesReturnsEmptyArrayWhenCookieAbsent(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getCookieParams')->willReturn([]);

        $result = $this->subject->getPreferences($request);

        self::assertSame([], $result);
    }

    #[Test]
    public function getPreferencesParsesCookieFromRequest(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getCookieParams')->willReturn([
            'maispace_consent' => '{"1": true, "2": false}',
        ]);

        $result = $this->subject->getPreferences($request);

        self::assertTrue($result['1']);
        self::assertFalse($result['2']);
    }
}
