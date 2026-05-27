<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Service;

use Maispace\MaiConsent\Domain\Repository\ConsentLogRepository;
use Maispace\MaiConsent\Service\ConsentLogRetentionService;
use PHPUnit\Framework\TestCase;

class ConsentLogRetentionServiceTest extends TestCase
{
    private const int SECONDS_PER_DAY = 86400;

    public function testCalculateCutoffSubtractsRetentionWindowFromReferenceTime(): void
    {
        $service = $this->createService();

        self::assertSame(
            1_000_000 - (7 * self::SECONDS_PER_DAY),
            $service->calculateCutoff(7, 1_000_000),
        );
    }

    public function testCalculateCutoffClampsRetentionToAtLeastOneDay(): void
    {
        $service = $this->createService();

        self::assertSame(
            1_000_000 - self::SECONDS_PER_DAY,
            $service->calculateCutoff(0, 1_000_000),
        );
        self::assertSame(
            1_000_000 - self::SECONDS_PER_DAY,
            $service->calculateCutoff(-30, 1_000_000),
        );
    }

    public function testCalculateCutoffUsesCurrentTimeWhenNowOmitted(): void
    {
        $service = $this->createService();

        $before = time();
        $cutoff = $service->calculateCutoff(30);
        $after = time();

        self::assertGreaterThanOrEqual($before - (30 * self::SECONDS_PER_DAY), $cutoff);
        self::assertLessThanOrEqual($after - (30 * self::SECONDS_PER_DAY), $cutoff);
    }

    public function testPurgeExpiredDeletesEntriesOlderThanCutoffAndReturnsCount(): void
    {
        $repository = $this->createMock(ConsentLogRepository::class);
        $repository->expects(self::once())
            ->method('deleteOlderThan')
            ->with(self::callback(function (int $cutoff): bool {
                $expected = time() - (30 * self::SECONDS_PER_DAY);

                return $cutoff <= $expected && $cutoff >= $expected - 5;
            }))
            ->willReturn(42);

        $service = new ConsentLogRetentionService($repository);

        self::assertSame(42, $service->purgeExpired(30));
    }

    private function createService(): ConsentLogRetentionService
    {
        return new ConsentLogRetentionService($this->createMock(ConsentLogRepository::class));
    }
}
