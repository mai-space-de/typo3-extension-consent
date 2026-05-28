<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Middleware;

use Maispace\MaiConsent\Domain\Repository\ConsentCategoryRepository;
use Maispace\MaiConsent\Domain\Repository\ConsentLogRepository;
use Maispace\MaiConsent\Middleware\ConsentApiMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class ConsentApiMiddlewareTest extends TestCase
{
    private ConsentApiMiddleware $subject;
    private \ReflectionMethod $anonymizeIp;
    private \ReflectionMethod $resolveIpAddress;

    protected function setUp(): void
    {
        $categoryRepository = $this->createMock(ConsentCategoryRepository::class);
        $logRepository      = $this->createMock(ConsentLogRepository::class);

        $this->subject        = new ConsentApiMiddleware($categoryRepository, $logRepository);
        $this->anonymizeIp    = new \ReflectionMethod(ConsentApiMiddleware::class, 'anonymizeIp');
        $this->resolveIpAddress = new \ReflectionMethod(ConsentApiMiddleware::class, 'resolveIpAddress');
    }

    // -------------------------------------------------------------------------
    // IPv4 anonymization
    // -------------------------------------------------------------------------

    #[Test]
    public function anonymizeIpv4AnonymizesLastOctet(): void
    {
        self::assertSame(
            '192.168.1.0',
            $this->anonymizeIp->invoke($this->subject, '192.168.1.123'),
        );
    }

    #[Test]
    public function anonymizeIpv4HandlesLoopback(): void
    {
        self::assertSame(
            '127.0.0.0',
            $this->anonymizeIp->invoke($this->subject, '127.0.0.1'),
        );
    }

    #[Test]
    public function anonymizeIpv4PreservesAlreadyZeroLastOctet(): void
    {
        self::assertSame(
            '10.0.0.0',
            $this->anonymizeIp->invoke($this->subject, '10.0.0.0'),
        );
    }

    #[Test]
    public function anonymizeIpv4HandlesClassAAddress(): void
    {
        self::assertSame(
            '10.20.30.0',
            $this->anonymizeIp->invoke($this->subject, '10.20.30.40'),
        );
    }

    // -------------------------------------------------------------------------
    // IPv6 anonymization
    // -------------------------------------------------------------------------

    #[Test]
    public function anonymizeIpv6AnonymizesLastGroup(): void
    {
        self::assertSame(
            '2001:db8::0',
            $this->anonymizeIp->invoke($this->subject, '2001:db8::1'),
        );
    }

    #[Test]
    public function anonymizeIpv6HandlesLoopback(): void
    {
        self::assertSame(
            '::0',
            $this->anonymizeIp->invoke($this->subject, '::1'),
        );
    }

    #[Test]
    public function anonymizeIpv6HandlesFullAddress(): void
    {
        self::assertSame(
            '2001:db8:0:0:0:0:0:0',
            $this->anonymizeIp->invoke($this->subject, '2001:db8:0:0:0:0:0:1'),
        );
    }

    #[Test]
    public function anonymizeIpv6HandlesLinkLocal(): void
    {
        self::assertSame(
            'fe80::0',
            $this->anonymizeIp->invoke($this->subject, 'fe80::dead'),
        );
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    #[Test]
    public function anonymizeIpReturnsEmptyStringForEmptyInput(): void
    {
        self::assertSame(
            '',
            $this->anonymizeIp->invoke($this->subject, ''),
        );
    }

    // -------------------------------------------------------------------------
    // resolveIpAddress
    // -------------------------------------------------------------------------

    #[Test]
    public function resolveIpAddressAnonymizesWhenEnabled(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.1.123']);

        $result = $this->resolveIpAddress->invoke($this->subject, $request);

        self::assertSame('192.168.1.0', $result);
    }

    #[Test]
    public function resolveIpAddressReturnsRawIpWhenDisabled(): void
    {
        $middleware = $this->getMockBuilder(ConsentApiMiddleware::class)
            ->onlyMethods(['isIpAnonymisationEnabled'])
            ->disableOriginalConstructor()
            ->getMock();

        $middleware->method('isIpAnonymisationEnabled')->willReturn(false);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.1.123']);

        $resolveIpAddress = new \ReflectionMethod(ConsentApiMiddleware::class, 'resolveIpAddress');
        $result = $resolveIpAddress->invoke($middleware, $request);

        self::assertSame('192.168.1.123', $result);
    }

    #[Test]
    public function resolveIpAddressReturnsEmptyStringForMissingServerParam(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn([]);

        $result = $this->resolveIpAddress->invoke($this->subject, $request);

        self::assertSame('', $result);
    }
}
