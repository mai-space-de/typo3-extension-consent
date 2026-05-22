<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Middleware;

use Maispace\MaiConsent\Domain\Repository\ConsentCategoryRepository;
use Maispace\MaiConsent\Domain\Repository\ConsentLogRepository;
use Maispace\MaiConsent\Middleware\ConsentApiMiddleware;
use PHPUnit\Framework\TestCase;

class ConsentApiMiddlewareTest extends TestCase
{
    private ConsentApiMiddleware $subject;
    private \ReflectionMethod $anonymizeIp;

    protected function setUp(): void
    {
        $categoryRepository = $this->createMock(ConsentCategoryRepository::class);
        $logRepository      = $this->createMock(ConsentLogRepository::class);

        $this->subject    = new ConsentApiMiddleware($categoryRepository, $logRepository);
        $this->anonymizeIp = new \ReflectionMethod(ConsentApiMiddleware::class, 'anonymizeIp');
    }

    // -------------------------------------------------------------------------
    // IPv4 anonymization
    // -------------------------------------------------------------------------

    public function testAnonymizeIpv4AnonymizesLastOctet(): void
    {
        self::assertSame(
            '192.168.1.0',
            $this->anonymizeIp->invoke($this->subject, '192.168.1.123'),
        );
    }

    public function testAnonymizeIpv4HandlesLoopback(): void
    {
        self::assertSame(
            '127.0.0.0',
            $this->anonymizeIp->invoke($this->subject, '127.0.0.1'),
        );
    }

    public function testAnonymizeIpv4PreservesAlreadyZeroLastOctet(): void
    {
        self::assertSame(
            '10.0.0.0',
            $this->anonymizeIp->invoke($this->subject, '10.0.0.0'),
        );
    }

    public function testAnonymizeIpv4HandlesClassAAddress(): void
    {
        self::assertSame(
            '10.20.30.0',
            $this->anonymizeIp->invoke($this->subject, '10.20.30.40'),
        );
    }

    // -------------------------------------------------------------------------
    // IPv6 anonymization
    // -------------------------------------------------------------------------

    public function testAnonymizeIpv6AnonymizesLastGroup(): void
    {
        self::assertSame(
            '2001:db8::0',
            $this->anonymizeIp->invoke($this->subject, '2001:db8::1'),
        );
    }

    public function testAnonymizeIpv6HandlesLoopback(): void
    {
        self::assertSame(
            '::0',
            $this->anonymizeIp->invoke($this->subject, '::1'),
        );
    }

    public function testAnonymizeIpv6HandlesFullAddress(): void
    {
        self::assertSame(
            '2001:db8:0:0:0:0:0:0',
            $this->anonymizeIp->invoke($this->subject, '2001:db8:0:0:0:0:0:1'),
        );
    }

    public function testAnonymizeIpv6HandlesLinkLocal(): void
    {
        self::assertSame(
            'fe80::0',
            $this->anonymizeIp->invoke($this->subject, 'fe80::dead'),
        );
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function testAnonymizeIpReturnsEmptyStringForEmptyInput(): void
    {
        self::assertSame(
            '',
            $this->anonymizeIp->invoke($this->subject, ''),
        );
    }
}
