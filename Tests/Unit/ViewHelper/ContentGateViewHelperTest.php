<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\ViewHelper;

use Maispace\MaiConsent\ViewHelper\ContentGateViewHelper;
use PHPUnit\Framework\TestCase;

final class ContentGateViewHelperTest extends TestCase
{
    public function testIframeSourceIsDeferredUntilConsent(): void
    {
        $html = '<iframe class="mai-video__frame" src="https://www.youtube-nocookie.com/embed/abc" title="Video"></iframe>';

        $deferred = ContentGateViewHelper::deferEmbeddedSources($html);

        self::assertStringNotContainsString(' src=', $deferred);
        self::assertStringContainsString('data-consent-src="https://www.youtube-nocookie.com/embed/abc"', $deferred);
        self::assertStringContainsString('class="mai-video__frame"', $deferred);
    }

    public function testLocalImagesStayAddressable(): void
    {
        $html = '<img src="/fileadmin/poster.jpg" alt="">';

        self::assertSame($html, ContentGateViewHelper::deferEmbeddedSources($html));
    }
}
