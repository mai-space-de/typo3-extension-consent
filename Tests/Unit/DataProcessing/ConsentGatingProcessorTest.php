<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Tests\Unit\DataProcessing;

use Maispace\MaispaceConsent\DataProcessing\ConsentGatingProcessor;
use Maispace\MaispaceConsent\Event\BeforeContentElementGatedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

#[CoversClass(ConsentGatingProcessor::class)]
final class ConsentGatingProcessorTest extends TestCase
{
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ConsentGatingProcessor $subject;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->subject = new ConsentGatingProcessor($this->eventDispatcher);
    }

    private function buildProcessedData(int $uid, string $categories): array
    {
        return [
            'data' => [
                'uid'                            => $uid,
                'tx_maispace_consent_categories' => $categories,
            ],
        ];
    }

    #[Test]
    public function setsNotGatedWhenNoCategoriesAssigned(): void
    {
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processedData = $this->buildProcessedData(1, '');

        $result = $this->subject->process($cObj, [], [], $processedData);

        self::assertFalse($result['maispace_consent']['isGated']);
        self::assertSame([], $result['maispace_consent']['categoryUids']);
        self::assertSame('', $result['maispace_consent']['categoryList']);
    }

    #[Test]
    public function setsGatedWhenCategoriesAssigned(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processedData = $this->buildProcessedData(5, '2,3');

        $result = $this->subject->process($cObj, [], [], $processedData);

        self::assertTrue($result['maispace_consent']['isGated']);
        self::assertSame([2, 3], $result['maispace_consent']['categoryUids']);
        self::assertSame('2,3', $result['maispace_consent']['categoryList']);
    }

    #[Test]
    public function setsNotGatedWhenEventSkipsGating(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (BeforeContentElementGatedEvent $event) {
                $event->skip();

                return $event;
            });

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processedData = $this->buildProcessedData(10, '1');

        $result = $this->subject->process($cObj, [], [], $processedData);

        self::assertFalse($result['maispace_consent']['isGated']);
        self::assertSame([], $result['maispace_consent']['categoryUids']);
    }

    #[Test]
    public function eventCanOverrideCategoryUids(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (BeforeContentElementGatedEvent $event) {
                $event->setCategoryUids([99]);

                return $event;
            });

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processedData = $this->buildProcessedData(7, '1,2');

        $result = $this->subject->process($cObj, [], [], $processedData);

        self::assertTrue($result['maispace_consent']['isGated']);
        self::assertSame([99], $result['maispace_consent']['categoryUids']);
        self::assertSame('99', $result['maispace_consent']['categoryList']);
    }

    #[Test]
    public function filterOutsZeroAndNegativeUids(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processedData = $this->buildProcessedData(3, '0,1,-2,3');

        $result = $this->subject->process($cObj, [], [], $processedData);

        self::assertTrue($result['maispace_consent']['isGated']);
        self::assertSame([1, 3], $result['maispace_consent']['categoryUids']);
    }

    #[Test]
    public function passesContentElementUidToEvent(): void
    {
        $capturedEvent = null;
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function (BeforeContentElementGatedEvent $event) use (&$capturedEvent) {
                $capturedEvent = $event;

                return $event;
            });

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processedData = $this->buildProcessedData(42, '5');

        $this->subject->process($cObj, [], [], $processedData);

        self::assertInstanceOf(BeforeContentElementGatedEvent::class, $capturedEvent);
        self::assertSame(42, $capturedEvent->getContentElementUid());
    }

    #[Test]
    public function preservesExistingProcessedData(): void
    {
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processedData = $this->buildProcessedData(1, '2');
        $processedData['some_other_key'] = 'some_value';

        $result = $this->subject->process($cObj, [], [], $processedData);

        self::assertSame('some_value', $result['some_other_key']);
    }
}
