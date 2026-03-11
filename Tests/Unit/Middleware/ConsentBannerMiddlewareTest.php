<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Tests\Unit\Middleware;

use Maispace\MaispaceConsent\Domain\Model\Category;
use Maispace\MaispaceConsent\Middleware\ConsentBannerMiddleware;
use Maispace\MaispaceConsent\Service\BannerRenderer;
use Maispace\MaispaceConsent\Service\CategoryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(ConsentBannerMiddleware::class)]
final class ConsentBannerMiddlewareTest extends TestCase
{
    private CategoryService&MockObject $categoryService;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private BannerRenderer&MockObject $bannerRenderer;
    private ConsentBannerMiddleware $subject;

    protected function setUp(): void
    {
        $this->categoryService = $this->createMock(CategoryService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->bannerRenderer = $this->createMock(BannerRenderer::class);

        $this->subject = new ConsentBannerMiddleware(
            $this->categoryService,
            $this->eventDispatcher,
            $this->bannerRenderer,
        );
    }

    private function buildResponse(string $contentType, string $body): ResponseInterface&MockObject
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaderLine')->with('Content-Type')->willReturn($contentType);
        $response->method('getBody')->willReturn($stream);

        return $response;
    }

    #[Test]
    public function passesThroughNonHtmlResponses(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->buildResponse('text/plain; charset=utf-8', 'plain text content');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $this->categoryService->expects(self::never())->method('getAllCategories');

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function passesThroughJsonResponses(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->buildResponse('application/json', '{"key": "value"}');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->willReturn($response);

        $this->categoryService->expects(self::never())->method('getAllCategories');

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function returnsUnmodifiedResponseWhenNoBodyTagFound(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $htmlWithoutBodyTag = '<html><head></head><div>No body tag here</div></html>';
        $response = $this->buildResponse('text/html; charset=utf-8', $htmlWithoutBodyTag);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $this->categoryService->expects(self::never())->method('getAllCategories');

        $result = $this->subject->process($request, $handler);

        // Should return the original response unchanged
        self::assertSame($response, $result);
    }

    #[Test]
    public function injectsContentBeforeBodyTagInHtmlResponses(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $htmlBody = '<html><head></head><body><p>Content</p></body></html>';

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($htmlBody);

        $originalResponse = $this->createMock(ResponseInterface::class);
        $originalResponse->method('getHeaderLine')->with('Content-Type')->willReturn('text/html; charset=utf-8');
        $originalResponse->method('getBody')->willReturn($stream);

        // The response with body will return a new response
        $modifiedResponse = $this->createMock(ResponseInterface::class);
        $originalResponse
            ->method('withBody')
            ->willReturn($modifiedResponse);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($originalResponse);

        $category = Category::fromRow([
            'uid'          => 1,
            'pid'          => 0,
            'name'         => 'Analytics',
            'description'  => 'Track page views',
            'is_essential' => 0,
            'sorting'      => 0,
        ]);

        $this->categoryService->method('getAllCategories')->willReturn([$category]);

        // Event dispatcher passes events through
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->bannerRenderer->method('renderBannerHtml')->willReturn('');
        $this->bannerRenderer->method('renderModalHtml')->willReturn('');
        $this->bannerRenderer->method('getJsPath')->willReturn('/typo3conf/ext/maispace_consent/Resources/Public/JavaScript/consent.js');

        $result = $this->subject->process($request, $handler);

        // The result should be the modified response (withBody was called)
        self::assertSame($modifiedResponse, $result);
    }

    #[Test]
    public function skipsInjectionWhenBannerEventIsDisabled(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $htmlBody = '<html><body><p>Content</p></body></html>';
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($htmlBody);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaderLine')->with('Content-Type')->willReturn('text/html');
        $response->method('getBody')->willReturn($stream);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $this->categoryService->method('getAllCategories')->willReturn([]);

        // Return a disabled BeforeBannerRenderedEvent
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) {
                if ($event instanceof \Maispace\MaispaceConsent\Event\BeforeBannerRenderedEvent) {
                    $event->disable();
                }

                return $event;
            });

        $result = $this->subject->process($request, $handler);

        // Original response returned (no modification)
        self::assertSame($response, $result);
    }
}
