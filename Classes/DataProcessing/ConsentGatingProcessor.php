<?php

declare(strict_types = 1);

namespace Maispace\MaiConsent\DataProcessing;

use Maispace\MaiConsent\Event\BeforeContentElementGatedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Adds consent-gating information to every content element's template variables.
 *
 * When a content element has one or more consent categories assigned via the
 * `tx_maiconsent_categories` field the processor sets
 * `maispace_consent.isGated = true` and `maispace_consent.categoryUids` so that
 * a layout override can wrap the element in a hidden container.
 *
 * Register in TypoScript:
 *
 *     lib.contentElement {
 *         dataProcessing {
 *             200 = Maispace\MaiConsent\DataProcessing\ConsentGatingProcessor
 *         }
 *     }
 */
class ConsentGatingProcessor implements DataProcessorInterface
{
    private const FIELD_CATEGORIES = 'tx_maiconsent_categories';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     *
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $showPlaceholder = isset($processorConfiguration['placeholder'])
            && (bool)$processorConfiguration['placeholder'];
        $placeholderPartial = (is_string($processorConfiguration['placeholderPartial'] ?? null)
            && $processorConfiguration['placeholderPartial'] !== '')
            ? $processorConfiguration['placeholderPartial']
            : 'Consent/Placeholder';

        $row = is_array($processedData['data'] ?? null) ? $processedData['data'] : [];
        $rawCategories = $row[self::FIELD_CATEGORIES] ?? '';

        $categoryUids = $this->parseCategoryUids(
            is_string($rawCategories) ? $rawCategories : ''
        );

        if ($categoryUids === []) {
            $processedData['mai_consent'] = [
                'isGated'            => false,
                'categoryUids'       => [],
                'categoryList'       => '',
                'showPlaceholder'    => false,
                'placeholderPartial' => $placeholderPartial,
            ];

            return $processedData;
        }

        $uidValue = $row['uid'] ?? null;
        $contentElementUid = is_int($uidValue) ? $uidValue : 0;

        $event = new BeforeContentElementGatedEvent($contentElementUid, $categoryUids);
        /** @var BeforeContentElementGatedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);

        if ($event->shouldSkip()) {
            $processedData['mai_consent'] = [
                'isGated'            => false,
                'categoryUids'       => [],
                'categoryList'       => '',
                'showPlaceholder'    => false,
                'placeholderPartial' => $placeholderPartial,
            ];

            return $processedData;
        }

        $resolvedUids = $event->getCategoryUids();

        // If a listener empties the UID list, treat as not-gated to avoid
        // permanently-hidden content with no path to become visible.
        if ($resolvedUids === []) {
            $processedData['mai_consent'] = [
                'isGated'            => false,
                'categoryUids'       => [],
                'categoryList'       => '',
                'showPlaceholder'    => false,
                'placeholderPartial' => $placeholderPartial,
            ];

            return $processedData;
        }

        $processedData['mai_consent'] = [
            'isGated'            => true,
            'categoryUids'       => $resolvedUids,
            'categoryList'       => implode(',', $resolvedUids),
            'showPlaceholder'    => $showPlaceholder,
            'placeholderPartial' => $placeholderPartial,
        ];

        return $processedData;
    }

    /**
     * Parses a comma-separated string of category UIDs into a list of positive integers.
     *
     * @return int[]
     */
    private function parseCategoryUids(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map('intval', explode(',', $raw)),
                static fn (int $uid) => $uid > 0
            )
        );
    }
}
