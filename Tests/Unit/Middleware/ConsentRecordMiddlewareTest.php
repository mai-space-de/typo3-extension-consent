<?php

declare(strict_types = 1);

namespace Maispace\MaiConsent\Tests\Unit\Middleware;

use Maispace\MaiConsent\Domain\Repository\StatisticRepository;
use Maispace\MaiConsent\Middleware\ConsentRecordMiddleware;
use Maispace\MaiConsent\Service\ConsentSettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(ConsentRecordMiddleware::class)]
final class ConsentRecordMiddlewareTest extends TestCase
{
    private StatisticRepository&MockObject $statisticRepository;
    private ConsentSettingsService&MockObject $consentSettingsService;
    private ConsentRecordMiddleware $subject;

    protected function setUp(): void
    {
        $this->statisticRepository = $this->createMock(StatisticRepository::class);
        $this->consentSettingsService = $this->createMock(ConsentSettingsService::class);

        $this->consentSettingsService
            ->method('getSettings')
            ->willReturn([
                'statistics' => ['enable' => 1, 'retentionDays' => 90],
            ]);

        $this->subject = new ConsentRecordMiddleware(
            $this->statisticRepository,
            $this->consentSettingsService,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildRequest(string $method, string $path, string $body = ''): ServerRequestInterface&MockObject
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);
        $stream->method('getContents')->willReturn($body);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getBody')->willReturn($stream);

        return $request;
    }

    // -------------------------------------------------------------------------
    // Pass-through
    // -------------------------------------------------------------------------

    #[Test]
    public function passesThroughGetRequests(): void
    {
        $request = $this->buildRequest('GET', '/maispace/consent/record');
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function passesThroughRequestsToOtherPaths(): void
    {
        $request = $this->buildRequest('POST', '/some/other/path', '{}');
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);
    }

    // -------------------------------------------------------------------------
    // Recording
    // -------------------------------------------------------------------------

    #[Test]
    public function recordsPreferencesForValidPayload(): void
    {
        $payload = json_encode(['preferences' => ['1' => true, '2' => false]]);
        $request = $this->buildRequest('POST', '/maispace/consent/record', (string)$payload);

        $this->statisticRepository->expects(self::exactly(2))->method('record');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $this->subject->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function returnsErrorForInvalidJsonPayload(): void
    {
        $request = $this->buildRequest('POST', '/maispace/consent/record', 'not-json');

        $this->statisticRepository->expects(self::never())->method('record');

        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $this->subject->process($request, $handler);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function returnsErrorWhenPreferencesKeyMissing(): void
    {
        $payload = json_encode(['other' => 'data']);
        $request = $this->buildRequest('POST', '/maispace/consent/record', (string)$payload);

        $this->statisticRepository->expects(self::never())->method('record');

        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $this->subject->process($request, $handler);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function skipsNonBooleanPreferenceValues(): void
    {
        $payload = json_encode(['preferences' => ['1' => 'yes', '2' => true]]);
        $request = $this->buildRequest('POST', '/maispace/consent/record', (string)$payload);

        // Only category 2 (boolean true) should be recorded
        $this->statisticRepository->expects(self::once())->method('record')->with(2, true);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->subject->process($request, $handler);
    }

    // -------------------------------------------------------------------------
    // statistics.enable
    // -------------------------------------------------------------------------

    #[Test]
    public function doesNotRecordWhenStatisticsIsDisabled(): void
    {
        $this->consentSettingsService = $this->createMock(ConsentSettingsService::class);
        $this->consentSettingsService
            ->method('getSettings')
            ->willReturn([
                'statistics' => ['enable' => 0, 'retentionDays' => 90],
            ]);

        $subject = new ConsentRecordMiddleware(
            $this->statisticRepository,
            $this->consentSettingsService,
        );

        $payload = json_encode(['preferences' => ['1' => true]]);
        $request = $this->buildRequest('POST', '/maispace/consent/record', (string)$payload);

        $this->statisticRepository->expects(self::never())->method('record');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $subject->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // statistics.retentionDays
    // -------------------------------------------------------------------------

    #[Test]
    public function callsDeleteOldEntriesAfterRecording(): void
    {
        $payload = json_encode(['preferences' => ['1' => true]]);
        $request = $this->buildRequest('POST', '/maispace/consent/record', (string)$payload);

        $this->statisticRepository->expects(self::once())->method('record');
        $this->statisticRepository->expects(self::once())->method('deleteOldEntries')->with(90);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->subject->process($request, $handler);
    }

    #[Test]
    public function doesNotCallDeleteOldEntriesWhenRetentionDaysIsZero(): void
    {
        $this->consentSettingsService = $this->createMock(ConsentSettingsService::class);
        $this->consentSettingsService
            ->method('getSettings')
            ->willReturn([
                'statistics' => ['enable' => 1, 'retentionDays' => 0],
            ]);

        $subject = new ConsentRecordMiddleware(
            $this->statisticRepository,
            $this->consentSettingsService,
        );

        $payload = json_encode(['preferences' => ['1' => true]]);
        $request = $this->buildRequest('POST', '/maispace/consent/record', (string)$payload);

        $this->statisticRepository->expects(self::once())->method('record');
        $this->statisticRepository->expects(self::never())->method('deleteOldEntries');

        $handler = $this->createMock(RequestHandlerInterface::class);

        $subject->process($request, $handler);
    }
}
