<?php

declare(strict_types = 1);

namespace Maispace\MaiConsent\Tests\Unit\DataProcessing;

use Maispace\MaiConsent\DataProcessing\ConsentGatingProcessor;
use Maispace\MaiConsent\Event\BeforeContentElementGatedEvent;
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
                'tx_maiconsent_categories'       => $categories,
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

        self::assertFalse($result['mai_consent']['isGated']);
        self::assertSame([], $result['mai_consent']['categoryUids']);
        self::assertSame('', $result['mai_consent']['categoryList']);
        self::assertFalse($result['mai_consent']['showPlaceholder']);
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

        self::assertTrue($result['mai_consent']['isGated']);
        self::assertSame([2, 3], $result['mai_consent']['categoryUids']);
        self::assertSame('2,3', $result['mai_consent']['categoryList']);
        self::assertFalse($result['mai_consent']['showPlaceholder']);
    }

    #[Test]
    public function setsGatedWithPlaceholderWhenProcessorConfigEnablesIt(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processedData = $this->buildProcessedData(5, '2');
        $processorConfig = [
            'placeholder'        => '1',
            'placeholderPartial' => 'Custom/Placeholder',
        ];

        $result = $this->subject->process($cObj, [], $processorConfig, $processedData);

        self::assertTrue($result['mai_consent']['isGated']);
        self::assertTrue($result['mai_consent']['showPlaceholder']);
        self::assertSame('Custom/Placeholder', $result['mai_consent']['placeholderPartial']);
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

        self::assertFalse($result['mai_consent']['isGated']);
        self::assertSame([], $result['mai_consent']['categoryUids']);
    }

    #[Test]
    public function setsNotGatedWhenEventEmptiesCategoryUids(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (BeforeContentElementGatedEvent $event) {
                $event->setCategoryUids([]);

                return $event;
            });

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $processedData = $this->buildProcessedData(10, '1,2');

        $result = $this->subject->process($cObj, [], [], $processedData);

        self::assertFalse($result['mai_consent']['isGated']);
        self::assertSame([], $result['mai_consent']['categoryUids']);
        self::assertSame('', $result['mai_consent']['categoryList']);
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

        self::assertTrue($result['mai_consent']['isGated']);
        self::assertSame([99], $result['mai_consent']['categoryUids']);
        self::assertSame('99', $result['mai_consent']['categoryList']);
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

        self::assertTrue($result['mai_consent']['isGated']);
        self::assertSame([1, 3], $result['mai_consent']['categoryUids']);
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
